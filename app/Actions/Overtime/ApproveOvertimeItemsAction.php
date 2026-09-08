<?php

namespace App\Actions\Overtime;

use App\Exceptions\OptimisticLockException;
use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\OvertimeSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveOvertimeItemsAction
{
    /**
     * Executes itemized partial or bulk approvals with optimistic lock verification.
     *
     * @param  array<int, array{item_id: int, action: string, rejection_reason?: string|null, lock_version?: int|null, notes?: string|null}>  $decisions
     *
     * @throws OptimisticLockException
     * @throws ValidationException
     */
    public function execute(int $submissionId, array $decisions, int $reviewerUserId): OvertimeSubmission
    {
        return DB::transaction(function () use ($submissionId, $decisions, $reviewerUserId) {
            /** @var OvertimeSubmission $submission */
            $submission = OvertimeSubmission::with('items')->findOrFail($submissionId);

            foreach ($decisions as $decision) {
                /** @var OvertimeItem $item */
                $item = OvertimeItem::where('id', $decision['item_id'])
                    ->where('overtime_submission_id', $submissionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Optimistic Locking Check (Concurrency Protection)
                if (isset($decision['lock_version']) && (int) $item->lock_version !== (int) $decision['lock_version']) {
                    throw new OptimisticLockException(
                        "Baris untuk NPK {$item->npk_snapshot} telah diperbarui oleh reviewer lain. Silakan muat ulang data."
                    );
                }

                $previousState = $item->toArray();
                $newStatus = strtoupper($decision['action']); // 'APPROVED' or 'REJECTED'

                if ($newStatus === 'REJECTED') {
                    $reason = isset($decision['rejection_reason']) ? trim((string) $decision['rejection_reason']) : '';
                    if ($reason === '' || mb_strlen($reason) < 5) {
                        throw ValidationException::withMessages([
                            'rejection_reason' => "Alasan penolakan wajib diisi (minimal 5 karakter) untuk NPK {$item->npk_snapshot}.",
                        ]);
                    }
                }

                $item->update([
                    'status' => $newStatus,
                    'reviewed_by_user_id' => $reviewerUserId,
                    'reviewed_at' => Carbon::now('Asia/Jakarta'),
                    'rejection_reason' => $newStatus === 'REJECTED' ? trim((string) $decision['rejection_reason']) : null,
                    'lock_version' => $item->lock_version + 1,
                ]);

                // Record Audit Event
                OvertimeItemAudit::create([
                    'overtime_item_id' => $item->id,
                    'action' => $newStatus,
                    'actor_user_id' => $reviewerUserId,
                    'previous_state' => $previousState,
                    'new_state' => $item->fresh()->toArray(),
                    'notes' => $newStatus === 'REJECTED'
                        ? trim((string) $decision['rejection_reason'])
                        : ($decision['notes'] ?? 'Disetujui melalui modal persetujuan.'),
                    'ip_address' => request()?->ip(),
                    'created_at' => Carbon::now('Asia/Jakarta'),
                ]);
            }

            // Synchronize Parent Header Status
            $allApproved = $submission->items()->where('status', '!=', 'APPROVED')->doesntExist();
            $allRejected = $submission->items()->where('status', '!=', 'REJECTED')->doesntExist();
            $hasApproved = $submission->items()->where('status', 'APPROVED')->exists();
            $hasRejected = $submission->items()->where('status', 'REJECTED')->exists();

            if ($allApproved) {
                $headerStatus = 'APPROVED';
            } elseif ($allRejected) {
                $headerStatus = 'REJECTED';
            } elseif ($hasApproved || $hasRejected) {
                $headerStatus = 'PARTIALLY_APPROVED';
            } else {
                $headerStatus = 'SUBMITTED';
            }

            $submission->update(['status' => $headerStatus]);

            // Dispatch Snapshot Rollup recalculation for the affected section
            $opDate = Carbon::parse($submission->operational_date);
            RecalculateMonthlyBurnSnapshotJob::dispatch(
                $submission->section_id,
                (int) $opDate->format('Y'),
                (int) $opDate->format('n')
            );

            return $submission->fresh(['items.employee', 'items.capexProject', 'spklDocument', 'section', 'department']);
        });
    }
}
