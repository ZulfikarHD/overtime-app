<?php

namespace App\Observers;

use App\Models\OvertimeItem;
use RuntimeException;

class OvertimeItemObserver
{
    /**
     * Handle the OvertimeItem "updating" event.
     * Enforces strict immutability of financial rate, total cost, and employee NPK snapshots.
     *
     * @throws RuntimeException
     */
    public function updating(OvertimeItem $item): void
    {
        if ($item->isDirty('hourly_rate_snapshot')) {
            throw new RuntimeException('The hourly_rate_snapshot is immutable and cannot be modified.');
        }

        if ($item->isDirty('total_cost_snapshot')) {
            throw new RuntimeException('The total_cost_snapshot is immutable and cannot be modified.');
        }

        if ($item->isDirty('npk_snapshot')) {
            throw new RuntimeException('The npk_snapshot is immutable and cannot be modified.');
        }
    }
}
