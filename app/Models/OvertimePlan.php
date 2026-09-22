<?php

namespace App\Models;

use Database\Factories\OvertimePlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $section_id
 * @property int $department_id
 * @property int $fiscal_year
 * @property int $fiscal_month
 * @property string $plan_code
 * @property string $status
 * @property int $submitted_by_user_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OvertimePlan extends Model
{
    /** @use HasFactory<OvertimePlanFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'section_id',
        'department_id',
        'fiscal_year',
        'fiscal_month',
        'plan_code',
        'status',
        'submitted_by_user_id',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'fiscal_month' => 'integer',
        ];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** @return HasMany<OvertimePlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OvertimePlanItem::class);
    }

    /**
     * Scope: only published plans.
     *
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'PUBLISHED');
    }

    /**
     * Scope: for a specific section + period.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForSectionPeriod(Builder $query, int $sectionId, int $year, int $month): void
    {
        $query->where('section_id', $sectionId)
            ->where('fiscal_year', $year)
            ->where('fiscal_month', $month);
    }
}
