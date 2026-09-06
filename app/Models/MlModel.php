<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $model_key
 * @property string $model_type
 * @property string $version
 * @property string $algorithm_name
 * @property array<string, mixed> $hyperparameters
 * @property array<string, mixed> $metrics
 * @property bool $is_active
 * @property Carbon|null $trained_at
 */
class MlModel extends Model
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
        'model_key',
        'model_type',
        'version',
        'algorithm_name',
        'hyperparameters',
        'metrics',
        'is_active',
        'trained_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hyperparameters' => 'array',
            'metrics' => 'array',
            'is_active' => 'boolean',
            'trained_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MlPrediction, $this>
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(MlPrediction::class);
    }

    /**
     * @return HasMany<MlAnomalyLog, $this>
     */
    public function anomalyLogs(): HasMany
    {
        return $this->hasMany(MlAnomalyLog::class);
    }

    /**
     * Scope a query to only include active ML models.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query for a specific model type.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForType(Builder $query, string $type): void
    {
        $query->where('model_type', $type);
    }
}
