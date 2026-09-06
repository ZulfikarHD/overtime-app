<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $department_id
 * @property int $section_id
 * @property int $fiscal_year
 * @property int $fiscal_month
 * @property string $planned_budget_hours
 * @property string $cumulative_actual_hours
 * @property string $cumulative_opex_hours
 * @property string $cumulative_capex_hours
 * @property string $burn_index_pct
 * @property string $burn_velocity
 * @property string $burn_zone
 * @property Carbon|null $last_recalculated_at
 */
class MonthlyBurnSnapshot extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'section_id',
        'fiscal_year',
        'fiscal_month',
        'planned_budget_hours',
        'cumulative_actual_hours',
        'cumulative_opex_hours',
        'cumulative_capex_hours',
        'burn_index_pct',
        'burn_velocity',
        'burn_zone',
        'last_recalculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'fiscal_month' => 'integer',
            'planned_budget_hours' => 'decimal:2',
            'cumulative_actual_hours' => 'decimal:2',
            'cumulative_opex_hours' => 'decimal:2',
            'cumulative_capex_hours' => 'decimal:2',
            'burn_index_pct' => 'decimal:2',
            'burn_velocity' => 'decimal:2',
            'last_recalculated_at' => 'datetime',
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
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Scope a query for a specific fiscal period.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForPeriod(Builder $query, int $year, int $month): void
    {
        $query->where('fiscal_year', $year)->where('fiscal_month', $month);
    }

    /**
     * Scope a query to snapshots in warning or danger burn zones.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWarningOrDanger(Builder $query): void
    {
        $query->whereIn('burn_zone', ['ZONE_3_WARNING', 'ZONE_4_POOR']);
    }

    /**
     * Scope a query for a specific section.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForSection(Builder $query, int $sectionId): void
    {
        $query->where('section_id', $sectionId);
    }

    /**
     * Scope a query for a specific department.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForDepartment(Builder $query, int $departmentId): void
    {
        $query->where('department_id', $departmentId);
    }
}
