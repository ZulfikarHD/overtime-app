<?php

namespace App\Observers;

use App\Models\OvertimeItemAudit;
use RuntimeException;

class OvertimeItemAuditObserver
{
    /**
     * Handle the OvertimeItemAudit "updating" event.
     * Enforces strict immutability - audit records cannot be modified.
     *
     * @throws RuntimeException
     */
    public function updating(OvertimeItemAudit $audit): void
    {
        throw new RuntimeException('Overtime item audit records are immutable and cannot be modified.');
    }

    /**
     * Handle the OvertimeItemAudit "deleting" event.
     * Enforces strict immutability - audit records cannot be deleted.
     *
     * @throws RuntimeException
     */
    public function deleting(OvertimeItemAudit $audit): void
    {
        throw new RuntimeException('Overtime item audit records are immutable and cannot be deleted.');
    }
}
