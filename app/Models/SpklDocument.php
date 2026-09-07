<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $overtime_submission_id
 * @property string|null $spkl_number
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size_bytes
 * @property string|null $mime_type
 * @property string $status
 * @property Carbon $due_date
 * @property Carbon|null $attached_at
 * @property int|null $attached_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SpklDocument extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'overtime_submission_id',
        'spkl_number',
        'file_path',
        'file_name',
        'file_size_bytes',
        'mime_type',
        'status',
        'due_date',
        'attached_at',
        'attached_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'due_date' => 'date',
            'attached_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function attachedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by_user_id');
    }

    /**
     * Scope a query to only include pending SPKL documents.
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'PENDING');
    }

    /**
     * Scope a query to only include attached SPKL documents.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAttached(Builder $query): void
    {
        $query->where('status', 'ATTACHED');
    }

    /**
     * Scope a query to only include verified SPKL documents.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('status', 'VERIFIED');
    }

    /**
     * Scope a query for overdue pending SPKL documents.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'PENDING')
            ->whereDate('due_date', '<', Carbon::now('Asia/Jakarta')->toDateString());
    }

    /**
     * Determine if the pending SPKL document is past its due date.
     */
    public function isDueOverdue(): bool
    {
        if ($this->status !== 'PENDING' || ! $this->due_date) {
            return false;
        }

        $nowWib = Carbon::now('Asia/Jakarta')->startOfDay();
        $due = Carbon::parse($this->due_date)->startOfDay();

        return $due->lt($nowWib);
    }
}
