<?php

namespace App\Actions\Overtime;

use App\Exceptions\OptimisticLockException;
use App\Models\SplEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approves or rejects individual SPL entries (the new approval flow).
 *
 * @param  array<int, array{entry_id: int, action: string, rejection_reason?: string|null, lock_version?: int|null}>  $decisions
 *
 * @throws OptimisticLockException
 * @throws ValidationException
 */
class ApproveSplEntriesAction
{
    /**
     * @param  list<array{entry_id: int, action: string, rejection_reason?: string|null, lock_version?: int|null}>  $decisions
     * @return array{approved: int, rejected: int}
     */
    public function execute(array $decisions, User $reviewer): array
    {
        $approved = 0;
        $rejected = 0;

        DB::transaction(function () use ($decisions, $reviewer, &$approved, &$rejected): void {
            foreach ($decisions as $decision) {
                /** @var SplEntry $entry */
                $entry = SplEntry::where('id', $decision['entry_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // Optimistic lock check
                if (isset($decision['lock_version']) && (int) $entry->lock_version !== (int) $decision['lock_version']) {
                    throw new OptimisticLockException(
                        "Data untuk {$entry->employee_name_snapshot} telah diubah oleh reviewer lain. Muat ulang halaman."
                    );
                }

                $newStatus = strtoupper((string) $decision['action']);

                if ($newStatus === 'REJECTED') {
                    $reason = trim((string) ($decision['rejection_reason'] ?? ''));
                    if ($reason === '' || mb_strlen($reason) < 5) {
                        throw ValidationException::withMessages([
                            'rejection_reason' => "Alasan penolakan wajib diisi (min 5 karakter) untuk {$entry->employee_name_snapshot}.",
                        ]);
                    }
                }

                $entry->update([
                    'status' => $newStatus,
                    'reviewed_by_user_id' => $reviewer->id,
                    'reviewed_at' => Carbon::now('Asia/Jakarta'),
                    'rejection_reason' => $newStatus === 'REJECTED' ? ($decision['rejection_reason'] ?? null) : null,
                    'lock_version' => $entry->lock_version + 1,
                ]);

                $newStatus === 'APPROVED' ? $approved++ : $rejected++;
            }
        });

        return ['approved' => $approved, 'rejected' => $rejected];
    }
}
