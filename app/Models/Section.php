<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $department_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Section extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'code',
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return HasMany<OvertimeBudget, $this>
     */
    public function overtimeBudgets(): HasMany
    {
        return $this->hasMany(OvertimeBudget::class);
    }

    /**
     * @return HasMany<OvertimeSubmission, $this>
     */
    public function overtimeSubmissions(): HasMany
    {
        return $this->hasMany(OvertimeSubmission::class);
    }

    /**
     * @return HasMany<MonthlyBurnSnapshot, $this>
     */
    public function monthlyBurnSnapshots(): HasMany
    {
        return $this->hasMany(MonthlyBurnSnapshot::class);
    }

    /**
     * Scope a query to only include active sections.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by department.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForDepartment(Builder $query, int $departmentId): void
    {
        $query->where('department_id', $departmentId);
    }
}
