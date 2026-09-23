<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $year
 * @property int $month 1–12
 * @property int $working_days X1: Jumlah Hari Kerja
 * @property int $production_volume X2: Volume Produksi (unit/bulan)
 * @property int $man_power X3: Total Man Power
 * @property int $overtime_index Y: Total Index Overtime
 */
class MlTrainingData extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'year',
        'month',
        'working_days',
        'production_volume',
        'man_power',
        'overtime_index',
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
            'overtime_index' => 'integer',
        ];
    }
}
