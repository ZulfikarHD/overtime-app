<?php

namespace App\Actions\Overtime;

use App\Models\SplEntry;
use App\Models\User;
use Throwable;

/**
 * Bulk-approves or bulk-rejects all PENDING SPL entries belonging to
 * one or more (section_id, realization_date) groups.
 */
class BulkApproveSplEntriesAction
{
    public function __construct(
        private readonly ApproveSplEntriesAction $approveAction,
    ) {}

    /**
     * @param  list<array{section_id: int, date: string}>  $groups  Each group identifies one "submission" (section+date pair)
     * @param  string  $action  'APPROVED' | 'REJECTED'
     * @return array{processed: int, skipped: int, skipped_details: list<array{group: string, reason: string}>, message: string}
     */
    public function execute(array $groups, string $action, ?string $rejectionReason, User $reviewer): array
    {
        $processed = 0;
        $skipped = 0;
        $skippedDetails = [];
        $normalizedAction = strtoupper($action);

        foreach ($groups as $group) {
            $sectionId = (int) $group['section_id'];
            $date = $group['date'];
            $groupKey = "Seksi #{$sectionId} – {$date}";

            // Only act on PENDING entries in this group
            $entries = SplEntry::where('section_id', $sectionId)
                ->whereDate('realization_date', $date)
                ->where('status', 'PENDING')
                ->get();

            if ($entries->isEmpty()) {
                $skipped++;
                $skippedDetails[] = ['group' => $groupKey, 'reason' => 'Tidak ada item pending.'];

                continue;
            }

            $decisions = $entries->map(fn (SplEntry $e) => [
                'entry_id' => $e->id,
                'action' => $normalizedAction,
                'rejection_reason' => $normalizedAction === 'REJECTED' ? $rejectionReason : null,
                'lock_version' => $e->lock_version,
            ])->values()->all();

            try {
                $result = $this->approveAction->execute($decisions, $reviewer);
                $processed += $result['approved'] + $result['rejected'];
            } catch (Throwable $e) {
                $skipped++;
                $skippedDetails[] = ['group' => $groupKey, 'reason' => $e->getMessage()];
            }
        }

        $verb = $normalizedAction === 'APPROVED' ? 'disetujui' : 'ditolak';
        $message = $skipped === 0
            ? __(':count item berhasil :verb.', ['count' => $processed, 'verb' => $verb])
            : __(':count item berhasil :verb; :skipped grup dilewati.', ['count' => $processed, 'verb' => $verb, 'skipped' => $skipped]);

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'skipped_details' => $skippedDetails,
            'message' => $message,
        ];
    }
}
