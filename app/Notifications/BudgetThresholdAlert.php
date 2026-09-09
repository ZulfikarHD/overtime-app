<?php

namespace App\Notifications;

use App\Models\MonthlyBurnSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BudgetThresholdAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  'warning'|'danger'  $alertLevel
     */
    public function __construct(
        public MonthlyBurnSnapshot $snapshot,
        public string $alertLevel,
        public float $thresholdPct,
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
        $this->snapshot->loadMissing(['section', 'department']);

        $sectionName = $this->snapshot->section?->name ?? 'Seksi';
        $burnIndexPct = number_format((float) $this->snapshot->burn_index_pct, 1, '.', '');
        $isDanger = $this->alertLevel === 'danger';

        $title = $isDanger
            ? "🚨 Kritis: Seksi {$sectionName} mencapai {$burnIndexPct}% (Melebihi anggaran)!"
            : "⚠️ Peringatan: Seksi {$sectionName} mencapai {$burnIndexPct}% (Mendekati batas anggaran)";

        $message = $isDanger
            ? "Seksi {$sectionName} telah mencapai {$burnIndexPct}% dari anggaran bulanan. Diperlukan evaluasi dan tindakan segera."
            : "Seksi {$sectionName} telah mencapai {$burnIndexPct}% dari anggaran bulanan (mendekati batas pagu).";

        return [
            'notification_type' => 'budget_threshold',
            'alert_level' => $this->alertLevel,
            'section_id' => $this->snapshot->section_id,
            'section_name' => $sectionName,
            'section_code' => $this->snapshot->section?->code ?? '',
            'department_id' => $this->snapshot->department_id,
            'department_name' => $this->snapshot->department?->name ?? '',
            'burn_index_pct' => (float) $this->snapshot->burn_index_pct,
            'threshold_pct' => $this->thresholdPct,
            'fiscal_year' => (int) $this->snapshot->fiscal_year,
            'fiscal_month' => (int) $this->snapshot->fiscal_month,
            'title' => $title,
            'message' => $message,
            'url' => "/dashboard/burn-index?tab=sections&section={$this->snapshot->section_id}",
        ];
    }
}
