<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $calendar_date
 * @property string $day_type
 * @property bool $is_holiday
 * @property string|null $holiday_name
 * @property string|null $description
 * @property Carbon|null $created_at
 */
class OperationalCalendar extends Model
{
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'calendar_date';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

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
        'calendar_date',
        'day_type',
        'is_holiday',
        'holiday_name',
        'description',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calendar_date' => 'date',
            'is_holiday' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OvertimeSubmission, $this>
     */
    public function overtimeSubmissions(): HasMany
    {
        return $this->hasMany(OvertimeSubmission::class, 'operational_date', 'calendar_date');
    }

    /**
     * Scope a query to only include normal workdays (HKN).
     *
     * @param  Builder<static>  $query
     */
    public function scopeWorkday(Builder $query): void
    {
        $query->where('day_type', 'HKN');
    }

    /**
     * Scope a query to only include holidays or rest days (HLR).
     *
     * @param  Builder<static>  $query
     */
    public function scopeHoliday(Builder $query): void
    {
        $query->where(function (Builder $sub) {
            $sub->where('day_type', 'HLR')->orWhere('is_holiday', true);
        });
    }

    /**
     * Scope a query for a specific date.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->whereDate('calendar_date', $date);
    }
}
