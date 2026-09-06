<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $npk
 * @property int $department_id
 * @property int $section_id
 * @property string $full_name
 * @property string $job_position
 * @property string $hourly_rate
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Employee extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'npk',
        'department_id',
        'section_id',
        'full_name',
        'job_position',
        'hourly_rate',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
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
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return HasMany<OvertimeItem, $this>
     */
    public function overtimeItems(): HasMany
    {
        return $this->hasMany(OvertimeItem::class);
    }

    /**
     * Scope a query to only include active employees.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by section.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForSection(Builder $query, int $sectionId): void
    {
        $query->where('section_id', $sectionId);
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

    /**
     * Composite scope: active employees within a specific section.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActiveInSection(Builder $query, int $sectionId): void
    {
        $query->where('section_id', $sectionId)->where('is_active', true);
    }
}
