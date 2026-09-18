<?php

namespace App\Models;

use Database\Factories\ExportLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $actor_user_id
 * @property string $resource_type
 * @property string $format
 * @property string $filename
 * @property int $record_count
 * @property array<string, mixed>|null $filters
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User $actor
 */
class ExportLog extends Model
{
    /** @use HasFactory<ExportLogFactory> */
    use HasFactory;

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
        'actor_user_id',
        'resource_type',
        'format',
        'filename',
        'record_count',
        'filters',
        'ip_address',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'record_count' => 'integer',
            'filters' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
