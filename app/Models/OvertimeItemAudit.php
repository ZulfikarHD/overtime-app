<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $overtime_item_id
 * @property string $action
 * @property int $actor_user_id
 * @property array<string, mixed>|null $previous_state
 * @property array<string, mixed> $new_state
 * @property string|null $notes
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
class OvertimeItemAudit extends Model
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
        'action',
        'actor_user_id',
        'previous_state',
        'new_state',
        'notes',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
            'new_state' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Scope a query for a specific audit action.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForAction(Builder $query, string $action): void
    {
        $query->where('action', $action);
    }

    /**
     * Scope a query for a specific actor.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForActor(Builder $query, int $userId): void
    {
        $query->where('actor_user_id', $userId);
    }
}
