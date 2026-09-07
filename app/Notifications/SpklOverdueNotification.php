<?php

namespace App\Notifications;

use App\Models\OvertimeSubmission;
use App\Models\SpklDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SpklOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public OvertimeSubmission $submission,
        public SpklDocument $spklDocument,
        public int $overdueDays,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $operationalDate = $this->submission->operational_date instanceof \DateTimeInterface
            ? $this->submission->operational_date->format('Y-m-d')
            : (string) $this->submission->operational_date;

        $dueDate = $this->spklDocument->due_date instanceof \DateTimeInterface
            ? $this->spklDocument->due_date->format('Y-m-d')
            : (string) $this->spklDocument->due_date;

        return [
            'submission_id' => $this->submission->id,
            'submission_code' => $this->submission->submission_code,
            'section_id' => $this->submission->section_id,
            'section_name' => $this->submission->section?->name ?? 'Section',
            'operational_date' => $operationalDate,
            'due_date' => $dueDate,
            'overdue_days' => $this->overdueDays,
            'reminder_type' => 'overdue',
            'title' => 'SPKL Terlambat: '.$this->submission->submission_code,
            'message' => 'Dokumen SPKL untuk pengajuan '.$this->submission->submission_code.' telah melewati batas waktu '.$this->overdueDays.' hari.',
            'spkl_document_id' => $this->spklDocument->id,
            'total_hours' => (float) $this->submission->total_hours_cached,
        ];
    }
}
