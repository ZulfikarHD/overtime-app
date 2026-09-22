<?php

namespace App\Models;

use Database\Factories\SplEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $employee_id
 * @property string $npk_snapshot
 * @property string $employee_name_snapshot
 * @property int|null $section_id
 * @property int|null $department_id
 * @property string|null $section_name_snapshot
 * @property string|null $department_name_snapshot
 * @property Carbon $realization_date
 * @property string $day_type
 * @property string $start_time
 * @property string $end_time
 * @property string $total_hours
 * @property string|null $jenis_pekerjaan
 * @property int|null $type_ot_code
 * @property string|null $keterangan_lembur
 * @property string|null $description
 * @property string|null $action
 * @property int $imported_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SplEntry extends Model
{
    /** @use HasFactory<SplEntryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'employee_id',
        'npk_snapshot',
        'employee_name_snapshot',
        'section_id',
        'department_id',
        'section_name_snapshot',
        'department_name_snapshot',
        'realization_date',
        'day_type',
        'start_time',
        'end_time',
        'total_hours',
        'jenis_pekerjaan',
        'type_ot_code',
        'keterangan_lembur',
        'description',
        'action',
        'imported_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'realization_date' => 'date',
            'total_hours' => 'decimal:2',
            'type_ot_code' => 'integer',
        ];
    }

    /**
     * Derive the overtime category label (A/B/C/D) from type_ot_code.
     * A = Production (61,62), B = TPM (65,66), C = Project/Kaizen (67,68), D = Others
     */
    public function getCategoryAttribute(): string
    {
        return match (true) {
            in_array($this->type_ot_code, [61, 62]) => 'A',
            in_array($this->type_ot_code, [65, 66]) => 'B',
            in_array($this->type_ot_code, [67, 68]) => 'C',
            default => 'D',
        };
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<User, $this> */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    /**
     * Scope: entries for a specific date range.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForMonth(Builder $query, int $year, int $month): void
    {
        $query->whereYear('realization_date', $year)
            ->whereMonth('realization_date', $month);
    }
}
