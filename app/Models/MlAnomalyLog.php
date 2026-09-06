<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $overtime_item_id
 * @property int $ml_model_id
 * @property string $anomaly_score
 * @property array<string, mixed> $anomaly_reasons
 * @property bool $is_dismissed
 * @property int|null $dismissed_by_user_id
 * @property Carbon|null $dismissed_at
 * @property string|null $dismissal_note
 * @property Carbon|null $created_at
 */
class MlAnomalyLog extends Model
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
        'overtime_item_id',
        'ml_model_id',
        'anomaly_score',
        'anomaly_reasons',
        'is_dismissed',
        'dismissed_by_user_id',
        'dismissed_at',
        'dismissal_note',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anomaly_score' => 'decimal:4',
            'anomaly_reasons' => 'array',
            'is_dismissed' => 'boolean',
            'dismissed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OvertimeItem, $this>
     */
    public function overtimeItem(): BelongsTo
    {
        return $this->belongsTo(OvertimeItem::class);
    }

    /**
     * @return BelongsTo<MlModel, $this>
     */
    public function mlModel(): BelongsTo
    {
        return $this->belongsTo(MlModel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dismissedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by_user_id');
    }

    /**
     * Scope a query to only include pending (non-dismissed) anomaly logs.
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('is_dismissed', false);
    }

    /**
     * Scope a query to only include dismissed anomaly logs.
     *
     * @param  Builder<static>  $query
     */
    public function scopeDismissed(Builder $query): void
    {
        $query->where('is_dismissed', true);
    }

    /**
     * Scope a query for high anomaly scores exceeding threshold.
     *
     * @param  Builder<static>  $query
     */
    public function scopeHighAnomaly(Builder $query, float $threshold = 0.8): void
    {
        $query->where('anomaly_score', '>=', $threshold);
    }
}
