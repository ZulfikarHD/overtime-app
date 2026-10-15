<?php

namespace App\Enums;

/**
 * Lifecycle of an Early Warning alert follow-up (5W2H).
 *
 * A sent follow-up is read-only; there is no approval workflow.
 */
enum AlertFollowUpStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Terkirim',
        };
    }
}
