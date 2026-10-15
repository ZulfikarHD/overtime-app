<?php

namespace App\Models;

use App\Enums\AlertCause;
use Database\Factories\AlertCausePicMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PIC department the Early Warning follow-up generator proposes for a detected cause (E10-08).
 *
 * @property int $id
 * @property AlertCause $cause
 * @property int|null $pic_department_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Department|null $picDepartment
 */
class AlertCausePicMapping extends Model
{
    /** @use HasFactory<AlertCausePicMappingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cause',
        'pic_department_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cause' => AlertCause::class,
            'pic_department_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function picDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'pic_department_id');
    }
}
