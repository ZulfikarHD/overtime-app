<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ml_model_id
 * @property string $target_type
 * @property int $target_id
 * @property string $prediction_horizon
 * @property string $predicted_value
 * @property string|null $confidence_interval_lower
 * @property string|null $confidence_interval_upper
 * @property string|null $risk_score
 * @property string|null $risk_level
 * @property array<string, mixed>|null $feature_impact_json
 * @property bool $fallback_used
 * @property Carbon|null $created_at
 */
class MlPrediction extends Model
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
        'ml_model_id',
        'target_type',
        'target_id',
        'prediction_horizon',
        'predicted_value',
        'confidence_interval_lower',
        'confidence_interval_upper',
        'risk_score',
        'risk_level',
        'feature_impact_json',
        'fallback_used',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'predicted_value' => 'decimal:2',
            'confidence_interval_lower' => 'decimal:2',
            'confidence_interval_upper' => 'decimal:2',
            'risk_score' => 'decimal:4',
            'feature_impact_json' => 'array',
            'fallback_used' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MlModel, $this>
     */
    public function mlModel(): BelongsTo
    {
        return $this->belongsTo(MlModel::class);
    }

    /**
     * Scope a query for a specific target entity.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForTarget(Builder $query, string $type, int $id): void
    {
        $query->where('target_type', $type)->where('target_id', $id);
    }

    /**
     * Scope a query to high risk predictions.
     *
     * @param  Builder<static>  $query
     */
    public function scopeHighRisk(Builder $query): void
    {
        $query->where('risk_level', 'HIGH');
    }

    /**
     * Scope a query to order by most recent created_at.
     *
     * @param  Builder<static>  $query
     */
    public function scopeRecent(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }
}
