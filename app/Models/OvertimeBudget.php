<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $department_id
 * @property int|null $section_id
 * @property int $fiscal_year
 * @property int $fiscal_month
 * @property string $planned_hours
 * @property string $planned_cost_idr
 * @property string $week1_planned_hours
 * @property string $week2_planned_hours
 * @property string $week3_planned_hours
 * @property string $week4_planned_hours
 * @property string $week5_planned_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OvertimeBudget extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'section_id',
        'fiscal_year',
        'fiscal_month',
        'planned_hours',
        'planned_cost_idr',
        'week1_planned_hours',
        'week2_planned_hours',
        'week3_planned_hours',
        'week4_planned_hours',
        'week5_planned_hours',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'fiscal_month' => 'integer',
            'planned_hours' => 'decimal:2',
            'planned_cost_idr' => 'decimal:2',
            'week1_planned_hours' => 'decimal:2',
            'week2_planned_hours' => 'decimal:2',
            'week3_planned_hours' => 'decimal:2',
            'week4_planned_hours' => 'decimal:2',
            'week5_planned_hours' => 'decimal:2',
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
     * Scope a query for a specific department.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForDepartment(Builder $query, int $deptId): void
    {
        $query->where('department_id', $deptId);
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
}
