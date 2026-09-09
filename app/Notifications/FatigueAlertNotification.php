<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FatigueAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Employee $employee,
        public int $consecutiveWeeks,
        public float $weeklyHours,
        public float $weeklyLimit,
        public int $fiscalYear,
        public int $fiscalMonth,
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
        $this->employee->loadMissing(['section', 'department']);

        $employeeName = $this->employee->full_name;
        $npk = $this->employee->npk;
        $sectionName = $this->employee->section?->name ?? 'Seksi';
        $departmentName = $this->employee->department?->name ?? 'Departemen';
        $hoursStr = number_format($this->weeklyHours, 1, '.', '');
        $limitStr = number_format($this->weeklyLimit, 1, '.', '');

        $title = "🚨 Peringatan Kelelahan: {$employeeName} melebihi batas lembur {$this->consecutiveWeeks} minggu berturut-turut!";
        $message = "Karyawan {$employeeName} (NPK: {$npk}) telah melebihi batas lembur mingguan ({$hoursStr}/{$limitStr} jam) selama {$this->consecutiveWeeks} minggu berturut-turut. Evaluasi alokasi shift untuk mencegah risiko kelelahan dan kecelakaan kerja.";

        return [
            'notification_type' => 'fatigue_alert',
            'alert_level' => 'danger',
            'employee_id' => $this->employee->id,
            'employee_npk' => $npk,
            'employee_name' => $employeeName,
            'section_id' => $this->employee->section_id,
            'section_name' => $sectionName,
            'department_id' => $this->employee->department_id,
            'department_name' => $departmentName,
            'consecutive_weeks' => $this->consecutiveWeeks,
            'weekly_hours' => $this->weeklyHours,
            'weekly_limit' => $this->weeklyLimit,
            'fiscal_year' => $this->fiscalYear,
            'fiscal_month' => $this->fiscalMonth,
            'title' => $title,
            'message' => $message,
            'url' => "/reports/employees/{$npk}?tab=overview",
        ];
    }
}
