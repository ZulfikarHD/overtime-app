<?php

namespace App\Models;

use Database\Factories\UserAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_user_id
 * @property string $action
 * @property string|null $previous_role
 * @property string|null $new_role
 * @property array<string, mixed>|null $details
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read User|null $actor
 */
class UserAudit extends Model
{
    /** @use HasFactory<UserAuditFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'actor_user_id',
        'action',
        'previous_role',
        'new_role',
        'details',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
