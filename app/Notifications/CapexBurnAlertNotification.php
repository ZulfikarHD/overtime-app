<?php

namespace App\Notifications;

use App\Models\CapexProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CapexBurnAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public CapexProject $project,
        public float $burnIndexPct,
        public float $allocatedHours,
        public float $consumedHours,
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
        $this->project->loadMissing('department');

        $burnIndexFormatted = number_format($this->burnIndexPct, 1, '.', '');
        $consumedFormatted = number_format($this->consumedHours, 1, '.', '');
        $allocatedFormatted = number_format($this->allocatedHours, 1, '.', '');

        $title = "⚠️ Peringatan Alokasi CapEx: Proyek [{$this->project->project_code}] telah mencapai {$burnIndexFormatted}%";
        $message = "Proyek [{$this->project->project_code}] {$this->project->name} telah mengonsumsi {$burnIndexFormatted}% dari alokasi jam kerja ({$consumedFormatted} / {$allocatedFormatted} jam).";

        return [
            'notification_type' => 'capex_burn_alert',
            'project_id' => $this->project->id,
            'project_code' => $this->project->project_code,
            'project_name' => $this->project->name,
            'department_id' => $this->project->department_id,
            'department_name' => $this->project->department?->name ?? '',
            'burn_index_pct' => $this->burnIndexPct,
            'allocated_hours' => $this->allocatedHours,
            'consumed_hours' => $this->consumedHours,
            'title' => $title,
            'message' => $message,
            'url' => "/admin/capex-projects/{$this->project->id}",
        ];
    }
}
