<?php

namespace App\Models;

use App\Enums\AlertFollowUpStatus;
use App\Enums\BurnZone;
use Database\Factories\AlertFollowUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 5W2H follow-up of an Early Warning alert.
 *
 * @property int $id
 * @property int|null $department_id
 * @property int|null $section_id
 * @property Carbon|null $alert_date
 * @property int $fiscal_year
 * @property int $fiscal_month
 * @property BurnZone $zone
 * @property string $index_pct
 * @property int $streak_days
 * @property string|null $what_text
 * @property string|null $why_text
 * @property Carbon|null $when_date
 * @property string|null $where_location
 * @property int|null $who_department_id
 * @property string|null $how_text
 * @property int $how_much_cost_idr
 * @property string $how_much_hours
 * @property AlertFollowUpStatus $status
 * @property int $created_by
 * @property int|null $sent_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $department
 * @property-read Section|null $section
 * @property-read Department|null $whoDepartment
 * @property-read User $creator
 * @property-read User|null $sender
 */
class AlertFollowUp extends Model
{
    /** @use HasFactory<AlertFollowUpFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'section_id',
        'alert_date',
        'fiscal_year',
        'fiscal_month',
        'zone',
        'index_pct',
        'streak_days',
        'what_text',
        'why_text',
        'when_date',
        'where_location',
        'who_department_id',
        'how_text',
        'how_much_cost_idr',
        'how_much_hours',
        'status',
        'created_by',
        'sent_by',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alert_date' => 'date',
            'fiscal_year' => 'integer',
            'fiscal_month' => 'integer',
            'zone' => BurnZone::class,
            'index_pct' => 'decimal:2',
            'streak_days' => 'integer',
            'when_date' => 'date',
            'who_department_id' => 'integer',
            'how_much_cost_idr' => 'integer',
            'how_much_hours' => 'decimal:2',
            'status' => AlertFollowUpStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function isSent(): bool
    {
        return $this->status === AlertFollowUpStatus::Sent;
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function whoDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'who_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
