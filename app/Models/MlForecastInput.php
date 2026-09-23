<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $year
 * @property int $month 1–12
 * @property int $working_days X1: Hari Kerja (planned)
 * @property int $production_volume X2: Volume Produksi (planned)
 * @property int $man_power X3: Man Power (planned)
 * @property int|null $actual_overtime_index Y aktual (filled when available)
 */
class MlForecastInput extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'year',
        'month',
        'working_days',
        'production_volume',
        'man_power',
        'actual_overtime_index',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'working_days' => 'integer',
            'production_volume' => 'integer',
            'man_power' => 'integer',
            'actual_overtime_index' => 'integer',
        ];
    }
}
