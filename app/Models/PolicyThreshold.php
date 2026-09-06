<?php

namespace App\Models;

use Database\Factories\PolicyThresholdFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $department_id
 * @property string $weekly_soft_limit_hours
 * @property int $consecutive_weeks_alert
 * @property int $spkl_grace_period_days
 * @property string $burn_warning_pct
 * @property string $burn_danger_pct
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PolicyThreshold extends Model
{
    /** @use HasFactory<PolicyThresholdFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'weekly_soft_limit_hours',
        'consecutive_weeks_alert',
        'spkl_grace_period_days',
        'burn_warning_pct',
        'burn_danger_pct',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekly_soft_limit_hours' => 'decimal:1',
            'consecutive_weeks_alert' => 'integer',
            'spkl_grace_period_days' => 'integer',
            'burn_warning_pct' => 'decimal:2',
            'burn_danger_pct' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Scope query to plant-level default thresholds (where department_id is null).
     *
     * @param  Builder<static>  $query
     */
    public function scopePlantDefault(Builder $query): void
    {
        $query->whereNull('department_id');
    }

    /**
     * Scope query to a specific department.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForDepartment(Builder $query, ?int $departmentId): void
    {
        $query->where('department_id', $departmentId);
    }
}
