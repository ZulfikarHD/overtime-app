<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $cost_center_code
 * @property string $default_hourly_rate
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Department extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'cost_center_code',
        'default_hourly_rate',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return HasMany<PolicyThreshold, $this>
     */
    public function policyThresholds(): HasMany
    {
        return $this->hasMany(PolicyThreshold::class);
    }

    /**
     * @return HasMany<CapexProject, $this>
     */
    public function capexProjects(): HasMany
    {
        return $this->hasMany(CapexProject::class);
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
     * Scope a query to only include active departments.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
