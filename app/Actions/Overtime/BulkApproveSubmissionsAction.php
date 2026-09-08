<?php

namespace App\Actions\Overtime;

use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\User;
use Throwable;

class BulkApproveSubmissionsAction
{
    public function __construct(
        public ApproveOvertimeItemsAction $approveOvertimeItemsAction,
    ) {}

    /**
     * Executes bulk approval or rejection across multiple submissions with per-submission isolation.
     *
     * @param  list<int>  $submissionIds
     * @return array{
     *     processed_submissions_count: int,
     *     skipped_submissions_count: int,
     *     processed_items_count: int,
     *     skipped_items_count: int,
     *     processed_submission_ids: list<int>,
     *     skipped: list<array{id: int, code: string, reason: string}>,
     *     message: string
     * }
     */
    public function execute(array $submissionIds, string $action, ?string $rejectionReason, User $reviewer): array
    {
        $normalizedAction = strtoupper($action);

        $submissions = OvertimeSubmission::with('items')
            ->whereIn('id', $submissionIds)
            ->get()
            ->keyBy('id');

        $processedSubmissionsCount = 0;
        $skippedSubmissionsCount = 0;
        $processedItemsCount = 0;
        $skippedItemsCount = 0;
        $processedSubmissionIds = [];
        $skipped = [];

        foreach ($submissionIds as $subId) {
            /** @var OvertimeSubmission|null $submission */
            $submission = $submissions->get($subId);

            if (! $submission) {
                $skippedSubmissionsCount++;
                $skipped[] = [
                    'id' => $subId,
                    'code' => "ID #{$subId}",
                    'reason' => __('Pengajuan tidak ditemukan.'),
                ];

                continue;
            }

            // Role department check for Manager
            if ($reviewer->isManager() && $reviewer->department_id !== null && $submission->department_id !== $reviewer->department_id) {
                $skippedSubmissionsCount++;
                $itemCount = $submission->items->count();
                $skippedItemsCount += $itemCount;
                $skipped[] = [
                    'id' => $submission->id,
                    'code' => $submission->submission_code,
                    'reason' => __('Pengajuan bukan bagian dari departemen Anda.'),
                ];

                continue;
            }

            // Determine processable items
            $targetItems = $submission->items->filter(function (OvertimeItem $item) use ($normalizedAction) {
                if ($normalizedAction === 'APPROVED') {
                    return $item->status !== 'APPROVED';
                }

                // REJECTED: cannot reject already approved items (immutable) and no need to reject already rejected
                return $item->status === 'PENDING';
            });

            if ($targetItems->isEmpty()) {
                $skippedSubmissionsCount++;
                $skipped[] = [
                    'id' => $submission->id,
                    'code' => $submission->submission_code,
                    'reason' => $normalizedAction === 'APPROVED'
                        ? __('Semua item dalam pengajuan ini sudah disetujui.')
                        : __('Tidak ada item pending yang dapat ditolak.'),
                ];

                continue;
            }

            $decisions = $targetItems->map(fn (OvertimeItem $item) => [
                'item_id' => $item->id,
                'action' => $normalizedAction,
                'rejection_reason' => $normalizedAction === 'REJECTED' ? trim((string) $rejectionReason) : null,
                'lock_version' => $item->lock_version,
                'notes' => $normalizedAction === 'REJECTED'
                    ? trim((string) $rejectionReason)
                    : __('Disetujui melalui persetujuan massal.'),
            ])->values()->all();

            try {
                $this->approveOvertimeItemsAction->execute($submission->id, $decisions, $reviewer->id);
                $processedSubmissionsCount++;
                $processedItemsCount += count($decisions);
                $processedSubmissionIds[] = $submission->id;
            } catch (Throwable $e) {
                $skippedSubmissionsCount++;
                $skippedItemsCount += count($decisions);
                $skipped[] = [
                    'id' => $submission->id,
                    'code' => $submission->submission_code,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $actionVerb = $normalizedAction === 'APPROVED' ? __('disetujui') : __('ditolak');

        if ($skippedSubmissionsCount === 0) {
            $message = __('Persetujuan massal selesai: :items item berhasil :verb (:subs pengajuan).', [
                'items' => $processedItemsCount,
                'verb' => $actionVerb,
                'subs' => $processedSubmissionsCount,
            ]);
        } else {
            $message = __('Proses massal: :items item berhasil :verb (:skipped item dilewati).', [
                'items' => $processedItemsCount,
                'verb' => $actionVerb,
                'skipped' => $skippedItemsCount,
            ]);
        }

        return [
            'processed_submissions_count' => $processedSubmissionsCount,
            'skipped_submissions_count' => $skippedSubmissionsCount,
            'processed_items_count' => $processedItemsCount,
            'skipped_items_count' => $skippedItemsCount,
            'processed_submission_ids' => $processedSubmissionIds,
            'skipped' => $skipped,
            'message' => $message,
        ];
    }
}
