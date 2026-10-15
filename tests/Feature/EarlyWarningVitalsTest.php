<?php

use App\Models\MlTrainingData;
use App\Models\Section;
use App\Services\EarlyWarningService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()->setLocale('en');

    $this->section = Section::factory()->create();
    $this->service = app(EarlyWarningService::class);
});

function vitalsMonth(int $year, int $month, int $volume, int $manpower): void
{
    MlTrainingData::factory()->create([
        'year' => $year,
        'month' => $month,
        'production_volume' => $volume,
        'man_power' => $manpower,
    ]);
}

test('both vitals compare the alert month with the previous month and carry the real period label', function () {
    vitalsMonth(2026, 9, 2000, 800);
    vitalsMonth(2026, 10, 2400, 840);

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));

    expect($vitals)->toBe([
        'volume_change_pct' => 20.0,
        'volume_period_label' => 'vs September 2026',
        'manpower_change_pct' => 5.0,
        'manpower_period_label' => 'vs September 2026 (plant-wide)',
    ]);
});

test('a decrease is a negative percentage', function () {
    vitalsMonth(2026, 9, 2000, 800);
    vitalsMonth(2026, 10, 1500, 760);

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));

    expect($vitals['volume_change_pct'])->toBe(-25.0)
        ->and($vitals['manpower_change_pct'])->toBe(-5.0);
});

test('the change is rounded to one decimal', function () {
    vitalsMonth(2026, 9, 3000, 700);
    vitalsMonth(2026, 10, 3100, 700);

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));

    expect($vitals['volume_change_pct'])->toBe(3.3);
});

test('without any monthly data every value is null with no label and nothing is made up', function () {
    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));

    expect($vitals)->toBe([
        'volume_change_pct' => null,
        'volume_period_label' => null,
        'manpower_change_pct' => null,
        'manpower_period_label' => null,
    ]);
});

test('a missing alert month or previous month gives null instead of a guess', function (array $months) {
    foreach ($months as [$year, $month]) {
        vitalsMonth($year, $month, 2000, 800);
    }

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));

    expect($vitals['volume_change_pct'])->toBeNull()
        ->and($vitals['volume_period_label'])->toBeNull()
        ->and($vitals['manpower_change_pct'])->toBeNull()
        ->and($vitals['manpower_period_label'])->toBeNull();
})->with([
    'only the alert month' => [[[2026, 10]]],
    'only the previous month' => [[[2026, 9]]],
    'only an unrelated month' => [[[2026, 8]]],
]);

test('a previous value of zero returns null instead of infinity or NaN', function () {
    vitalsMonth(2026, 9, 0, 0);
    vitalsMonth(2026, 10, 2400, 840);

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));

    expect($vitals['volume_change_pct'])->toBeNull()
        ->and($vitals['volume_period_label'])->toBeNull()
        ->and($vitals['manpower_change_pct'])->toBeNull()
        ->and($vitals['manpower_period_label'])->toBeNull();
});

test('one source can be available while the other is not', function () {
    vitalsMonth(2026, 9, 2000, 0);
    vitalsMonth(2026, 10, 2200, 800);

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-13', 'Asia/Jakarta'));

    expect($vitals['volume_change_pct'])->toBe(10.0)
        ->and($vitals['volume_period_label'])->toBe('vs September 2026')
        ->and($vitals['manpower_change_pct'])->toBeNull()
        ->and($vitals['manpower_period_label'])->toBeNull();
});

test('the previous month of January is December of the year before', function () {
    vitalsMonth(2025, 12, 1000, 500);
    vitalsMonth(2026, 1, 1100, 500);

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-01-05', 'Asia/Jakarta'));

    expect($vitals['volume_change_pct'])->toBe(10.0)
        ->and($vitals['volume_period_label'])->toBe('vs December 2025');
});

test('the period label follows the active locale', function () {
    vitalsMonth(2026, 9, 2000, 800);
    vitalsMonth(2026, 10, 2400, 800);
    app()->setLocale('id');

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-10-31', 'Asia/Jakarta'));

    expect($vitals['volume_period_label'])->toBe('vs September 2026')
        ->and($vitals['manpower_period_label'])->toBe('vs September 2026 (seluruh pabrik)');
});

test('the month of the alert date decides the comparison, even on the last day of a 31 day month', function () {
    vitalsMonth(2026, 7, 1000, 500);
    vitalsMonth(2026, 8, 1200, 500);

    $vitals = $this->service->vitalsFor($this->section, Carbon::parse('2026-08-31', 'Asia/Jakarta'));

    expect($vitals['volume_change_pct'])->toBe(20.0)
        ->and($vitals['volume_period_label'])->toBe('vs July 2026');
});
