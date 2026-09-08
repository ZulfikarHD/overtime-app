<?php

namespace App\Models;

use App\Observers\OvertimeItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $overtime_submission_id
 * @property int $employee_id
 * @property string $npk_snapshot
 * @property int|null $capex_project_id
 * @property string $hours_production
 * @property string $hours_tpm
 * @property string $hours_project
 * @property string $hours_others
 * @property string $total_hours
 * @property string $hourly_rate_snapshot
 * @property string $total_cost_snapshot
 * @property string|null $rca_category
 * @property string|null $rca_notes
 * @property string|null $task_description
 * @property string $status
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int $lock_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[ObservedBy([OvertimeItemObserver::class])]
class OvertimeItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'overtime_submission_id',
        'employee_id',
        'npk_snapshot',
        'capex_project_id',
        'hours_production',
        'hours_tpm',
        'hours_project',
        'hours_others',
        'hourly_rate_snapshot',
        'total_cost_snapshot',
        'rca_category',
        'rca_notes',
        'task_description',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'lock_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hours_production' => 'decimal:2',
            'hours_tpm' => 'decimal:2',
            'hours_project' => 'decimal:2',
            'hours_others' => 'decimal:2',
            'total_hours' => 'decimal:2',
            'hourly_rate_snapshot' => 'decimal:2',
            'total_cost_snapshot' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OvertimeSubmission, $this>
     */
    public function overtimeSubmission(): BelongsTo
    {
        return $this->belongsTo(OvertimeSubmission::class);
    }

    /**
     * Alias for overtimeSubmission relationship.
     *
     * @return BelongsTo<OvertimeSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->overtimeSubmission();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<CapexProject, $this>
     */
    public function capexProject(): BelongsTo
    {
        return $this->belongsTo(CapexProject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<OvertimeItemAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(OvertimeItemAudit::class);
    }

    /**
     * @return HasMany<MlAnomalyLog, $this>
     */
    public function anomalyLogs(): HasMany
    {
        return $this->hasMany(MlAnomalyLog::class);
    }

    /**
     * Scope a query to only include approved items.
     *
     * @param  Builder<static>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'APPROVED');
    }

    /**
     * Scope a query to only include pending items.
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'PENDING');
    }

    /**
     * Scope a query to only include rejected items.
     *
     * @param  Builder<static>  $query
     */
    public function scopeRejected(Builder $query): void
    {
        $query->where('status', 'REJECTED');
    }

    /**
     * Scope a query for a specific employee.
     *
     * @param  Builder<static>  $query
     */
    public function scopeByEmployee(Builder $query, int $employeeId): void
    {
        $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to items allocated to CapEx projects.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCapex(Builder $query): void
    {
        $query->whereNotNull('capex_project_id');
    }
}
