<?php

namespace App\Models;

use Database\Factories\CapexProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $project_code
 * @property string|null $asset_code
 * @property string $name
 * @property int $department_id
 * @property string $allocated_labor_hours
 * @property string $allocated_labor_budget_idr
 * @property string $physical_progress_pct
 * @property string $status
 * @property Carbon $start_date
 * @property Carbon $target_end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CapexProject extends Model
{
    /** @use HasFactory<CapexProjectFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_code',
        'asset_code',
        'name',
        'department_id',
        'allocated_labor_hours',
        'allocated_labor_budget_idr',
        'physical_progress_pct',
        'status',
        'start_date',
        'target_end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allocated_labor_hours' => 'decimal:2',
            'allocated_labor_budget_idr' => 'decimal:2',
            'physical_progress_pct' => 'decimal:2',
            'start_date' => 'date',
            'target_end_date' => 'date',
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
     * @return HasMany<OvertimeItem, $this>
     */
    public function overtimeItems(): HasMany
    {
        return $this->hasMany(OvertimeItem::class);
    }

    /**
     * Scope a query to only include active CapEx projects.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'ACTIVE');
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
