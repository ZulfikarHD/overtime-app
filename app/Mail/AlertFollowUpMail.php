<?php

namespace App\Mail;

use App\Models\AlertFollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email-only notification of an Early Warning follow-up (5W2H).
 */
class AlertFollowUpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public AlertFollowUp $followUp) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $this->followUp->loadMissing(['department:id,name', 'section:id,name']);

        $scope = $this->followUp->section?->name
            ?? $this->followUp->department?->name
            ?? __('Seluruh Pabrik');

        return new Envelope(
            subject: __('[SMARTIME] Tindak Lanjut Alert :zone — :scope', [
                'zone' => $this->followUp->zone->label(),
                'scope' => $scope,
            ]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $this->followUp->loadMissing([
            'department:id,name',
            'section:id,name',
            'whoDepartment:id,name',
            'sender:id,name',
        ]);

        return new Content(
            markdown: 'mail.alert-follow-up',
            with: [
                'followUp' => $this->followUp,
                'scopeLabel' => $this->followUp->section?->name
                    ?? $this->followUp->department?->name
                    ?? __('Seluruh Pabrik'),
                'costLabel' => 'Rp '.number_format($this->followUp->how_much_cost_idr, 0, ',', '.'),
                'hoursLabel' => number_format((float) $this->followUp->how_much_hours, 1, ',', '.'),
                'dashboardUrl' => route('dashboard'),
            ],
        );
    }
}
