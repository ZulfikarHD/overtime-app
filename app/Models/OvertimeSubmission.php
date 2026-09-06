<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $submission_code
 * @property Carbon $submission_date
 * @property Carbon $operational_date
 * @property string $day_type
 * @property int $department_id
 * @property int $section_id
 * @property int $submitted_by_user_id
 * @property string $status
 * @property string $total_hours_cached
 * @property string|null $submission_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OvertimeSubmission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'submission_code',
        'submission_date',
        'operational_date',
        'day_type',
        'department_id',
        'section_id',
        'submitted_by_user_id',
        'status',
        'total_hours_cached',
        'submission_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'operational_date' => 'date',
            'total_hours_cached' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<OperationalCalendar, $this>
     */
    public function operationalCalendar(): BelongsTo
    {
        return $this->belongsTo(OperationalCalendar::class, 'operational_date', 'calendar_date');
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
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasOne<SpklDocument, $this>
     */
    public function spklDocument(): HasOne
    {
        return $this->hasOne(SpklDocument::class);
    }

    /**
     * @return HasMany<OvertimeItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OvertimeItem::class);
    }

    /**
     * Scope a query to only include pending submissions (SUBMITTED or PARTIALLY_APPROVED).
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', ['SUBMITTED', 'PARTIALLY_APPROVED']);
    }

    /**
     * Scope a query to only include fully approved submissions.
     *
     * @param  Builder<static>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'APPROVED');
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

    /**
     * Scope a query for a specific operational calendar date.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->whereDate('operational_date', $date);
    }
}
