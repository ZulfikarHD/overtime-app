<?php

use App\Services\Analytics\PearsonCorrelationService;

beforeEach(function () {
    $this->service = new PearsonCorrelationService;
});

test('computes perfect positive correlation', function () {
    $x = [10, 20, 30, 40, 50];
    $y = [100, 200, 300, 400, 500];

    $r = $this->service->compute($x, $y);
    expect($r)->toBe(1.0);

    $reg = $this->service->linearRegression($x, $y);
    expect($reg['slope'])->toBe(10.0)
        ->and($reg['intercept'])->toBe(0.0)
        ->and($reg['r'])->toBe(1.0)
        ->and($reg['r_squared'])->toBe(1.0);
});

test('computes perfect negative correlation', function () {
    $x = [1, 2, 3, 4, 5];
    $y = [50, 40, 30, 20, 10];

    $r = $this->service->compute($x, $y);
    expect($r)->toBe(-1.0);

    $reg = $this->service->linearRegression($x, $y);
    expect($reg['slope'])->toBe(-10.0)
        ->and($reg['intercept'])->toBe(60.0)
        ->and($reg['r'])->toBe(-1.0);
});

test('handles empty arrays, mismatched counts, or single point safely', function () {
    expect($this->service->compute([], []))->toBe(0.0)
        ->and($this->service->compute([10], [20]))->toBe(0.0)
        ->and($this->service->compute([10, 20], [30]))->toBe(0.0);

    $reg = $this->service->linearRegression([10], [50]);
    expect($reg['slope'])->toBe(0.0)
        ->and($reg['r'])->toBe(0.0)
        ->and($reg['intercept'])->toBe(50.0);

    expect($this->service->computeTrendLine([10], [50]))->toBe([]);
});

test('handles zero variance in either array without division by zero', function () {
    $x = [10, 10, 10, 10];
    $y = [100, 200, 300, 400];

    expect($this->service->compute($x, $y))->toBe(0.0);

    $reg = $this->service->linearRegression($x, $y);
    expect($reg['slope'])->toBe(0.0)
        ->and($reg['r'])->toBe(0.0);

    expect($this->service->computeTrendLine($x, $y))->toBe([]);
});

test('computes realistic manufacturing scatter correlation and trend line', function () {
    // Production units vs overtime hours
    $production = [1200, 1350, 1420, 1500, 1610, 1750];
    $overtime = [180, 210, 225, 260, 290, 340];

    $r = $this->service->compute($production, $overtime);
    expect($r)->toBeGreaterThan(0.95);

    $trend = $this->service->computeTrendLine($production, $overtime);
    expect($trend)->toHaveCount(2)
        ->and($trend[0]['x'])->toBe(1200.0)
        ->and($trend[1]['x'])->toBe(1750.0)
        ->and($trend[0]['y'])->toBeLessThan($trend[1]['y']);
});
