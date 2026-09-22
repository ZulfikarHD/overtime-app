<?php

namespace App\Models;

use Database\Factories\OvertimePlanItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $overtime_plan_id
 * @property int $employee_id
 * @property string $npk_snapshot
 * @property Carbon $plan_date
 * @property string $day_type
 * @property string $hours_production
 * @property string $hours_tpm
 * @property string $hours_project
 * @property string $hours_others
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OvertimePlanItem extends Model
{
    /** @use HasFactory<OvertimePlanItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'overtime_plan_id',
        'employee_id',
        'npk_snapshot',
        'plan_date',
        'day_type',
        'hours_production',
        'hours_tpm',
        'hours_project',
        'hours_others',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'hours_production' => 'decimal:2',
            'hours_tpm' => 'decimal:2',
            'hours_project' => 'decimal:2',
            'hours_others' => 'decimal:2',
        ];
    }

    /**
     * Total planned hours for this item (virtual, computed in PHP).
     */
    public function getTotalHoursAttribute(): float
    {
        return (float) $this->hours_production
            + (float) $this->hours_tpm
            + (float) $this->hours_project
            + (float) $this->hours_others;
    }

    /** @return BelongsTo<OvertimePlan, $this> */
    public function overtimePlan(): BelongsTo
    {
        return $this->belongsTo(OvertimePlan::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
