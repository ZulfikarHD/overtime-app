<?php

namespace App\Models;

use Database\Factories\ShiftScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property string $shift_1_start
 * @property string $shift_1_end
 * @property string $shift_2_start
 * @property string $shift_2_end
 * @property string $shift_3_start
 * @property string $shift_3_end
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShiftSchedule extends Model
{
    /** @use HasFactory<ShiftScheduleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'effective_from',
        'effective_until',
        'shift_1_start',
        'shift_1_end',
        'shift_2_start',
        'shift_2_end',
        'shift_3_start',
        'shift_3_end',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to schedules active on a given date (inclusive).
     *
     * @param  Builder<static>  $query
     */
    public function scopeActiveOn(Builder $query, Carbon|string $date): void
    {
        $day = $date instanceof Carbon
            ? $date->copy()->timezone('Asia/Jakarta')->startOfDay()
            : Carbon::parse($date, 'Asia/Jakarta')->startOfDay();

        $query->whereDate('effective_from', '<=', $day)
            ->where(function (Builder $q) use ($day) {
                $q->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $day);
            });
    }

    /**
     * Format a stored TIME value as HH:MM for UI/API payloads.
     */
    public function formatTime(string $attribute): string
    {
        $value = (string) $this->getAttribute($attribute);

        return substr($value, 0, 5);
    }

    /**
     * @return list<array{number: int, start: string, end: string}>
     */
    public function shiftsPayload(): array
    {
        return [
            [
                'number' => 1,
                'start' => $this->formatTime('shift_1_start'),
                'end' => $this->formatTime('shift_1_end'),
            ],
            [
                'number' => 2,
                'start' => $this->formatTime('shift_2_start'),
                'end' => $this->formatTime('shift_2_end'),
            ],
            [
                'number' => 3,
                'start' => $this->formatTime('shift_3_start'),
                'end' => $this->formatTime('shift_3_end'),
            ],
        ];
    }
}
