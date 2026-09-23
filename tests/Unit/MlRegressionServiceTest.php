<?php

use App\Services\Analytics\MlRegressionService;

/**
 * Unit tests for MlRegressionService.
 * Reference values verified against docs/references/ml_references.xlsx.
 */

// ─── Test data (30 rows from the xlsx Data sheet) ────────────────────────────
function xlsxTrainingRows(): array
{
    return [
        ['working_days' => 20, 'production_volume' => 3107, 'man_power' => 845, 'overtime_index' => 81305],
        ['working_days' => 22, 'production_volume' => 2694, 'man_power' => 814, 'overtime_index' => 67057],
        ['working_days' => 19, 'production_volume' => 2211, 'man_power' => 798, 'overtime_index' => 39619],
        ['working_days' => 19, 'production_volume' => 2352, 'man_power' => 789, 'overtime_index' => 42895],
        ['working_days' => 16, 'production_volume' => 2008, 'man_power' => 756, 'overtime_index' => 36966],
        ['working_days' => 20, 'production_volume' => 2659, 'man_power' => 790, 'overtime_index' => 59948],
        ['working_days' => 19, 'production_volume' => 2839, 'man_power' => 790, 'overtime_index' => 82171],
        ['working_days' => 22, 'production_volume' => 3374, 'man_power' => 690, 'overtime_index' => 90534],
        ['working_days' => 22, 'production_volume' => 3208, 'man_power' => 690, 'overtime_index' => 100190],
        ['working_days' => 20, 'production_volume' => 3023, 'man_power' => 697, 'overtime_index' => 91437],
        ['working_days' => 23, 'production_volume' => 3237, 'man_power' => 700, 'overtime_index' => 76332],
        ['working_days' => 22, 'production_volume' => 3014, 'man_power' => 699, 'overtime_index' => 62074],
        ['working_days' => 18, 'production_volume' => 2617, 'man_power' => 694, 'overtime_index' => 89619],
        ['working_days' => 20, 'production_volume' => 2443, 'man_power' => 706, 'overtime_index' => 75216],
        ['working_days' => 20, 'production_volume' => 3004, 'man_power' => 706, 'overtime_index' => 78847],
        ['working_days' => 19, 'production_volume' => 2934, 'man_power' => 707, 'overtime_index' => 92254],
        ['working_days' => 17, 'production_volume' => 2717, 'man_power' => 707, 'overtime_index' => 68708],
        ['working_days' => 19, 'production_volume' => 2960, 'man_power' => 708, 'overtime_index' => 91078],
        ['working_days' => 19, 'production_volume' => 2832, 'man_power' => 794, 'overtime_index' => 73434],
        ['working_days' => 23, 'production_volume' => 3402, 'man_power' => 787, 'overtime_index' => 60522],
        ['working_days' => 21, 'production_volume' => 2747, 'man_power' => 775, 'overtime_index' => 44904],
        ['working_days' => 21, 'production_volume' => 2456, 'man_power' => 775, 'overtime_index' => 45540],
        ['working_days' => 22, 'production_volume' => 2463, 'man_power' => 733, 'overtime_index' => 38491],
        ['working_days' => 22, 'production_volume' => 1998, 'man_power' => 729, 'overtime_index' => 29413],
        ['working_days' => 20, 'production_volume' => 2485, 'man_power' => 730, 'overtime_index' => 62983],
        ['working_days' => 20, 'production_volume' => 2290, 'man_power' => 730, 'overtime_index' => 54317],
        ['working_days' => 19, 'production_volume' => 2173, 'man_power' => 730, 'overtime_index' => 62758],
        ['working_days' => 17, 'production_volume' => 3283, 'man_power' => 730, 'overtime_index' => 48410],
        ['working_days' => 21, 'production_volume' => 2889, 'man_power' => 704, 'overtime_index' => 88003],
        ['working_days' => 20, 'production_volume' => 3698, 'man_power' => 714, 'overtime_index' => 84449],
    ];
}

// ─── Descriptive Statistics ───────────────────────────────────────────────────

test('descriptive stats: Y mean equals known value from xlsx', function () {
    $service = new MlRegressionService;
    $y = array_column(xlsxTrainingRows(), 'overtime_index');
    $stats = $service->descriptiveStats($y);

    // xlsx Data sheet: mean Y = 67315.8
    expect($stats['mean'])->toBeBetween(67300.0, 67350.0)
        ->and($stats['n'])->toBe(30)
        ->and($stats['min'])->toBe(29413.0)
        ->and($stats['max'])->toBe(100190.0);
});

test('descriptive stats: X1 (working days) mean is ~20', function () {
    $service = new MlRegressionService;
    $x1 = array_column(xlsxTrainingRows(), 'working_days');
    $stats = $service->descriptiveStats($x1);

    expect($stats['mean'])->toBeBetween(19.5, 20.5);
});

test('descriptive stats: CV is proportion (not already multiplied by 100)', function () {
    $service = new MlRegressionService;
    $x1 = array_column(xlsxTrainingRows(), 'working_days');
    $stats = $service->descriptiveStats($x1);

    // X1 CV should be ~9%, i.e. value around 9.0 (stored as %)
    expect($stats['cv'])->toBeBetween(7.0, 12.0);
});

// ─── Pearson Correlation ──────────────────────────────────────────────────────

test('pearson r between X2 (volume) and Y is positive and > 0.4', function () {
    $service = new MlRegressionService;
    $rows = xlsxTrainingRows();
    $x1 = array_column($rows, 'working_days');
    $x2 = array_column($rows, 'production_volume');
    $x3 = array_column($rows, 'man_power');
    $y = array_column($rows, 'overtime_index');

    $result = $service->pearsonMatrix($x1, $x2, $x3, $y);
    $rX2Y = $result['matrix']['X2']['Y'];

    expect($rX2Y)->toBeGreaterThan(0.4);
});

test('pearson diagonal is 1.0', function () {
    $service = new MlRegressionService;
    $rows = xlsxTrainingRows();
    $result = $service->pearsonMatrix(
        array_column($rows, 'working_days'),
        array_column($rows, 'production_volume'),
        array_column($rows, 'man_power'),
        array_column($rows, 'overtime_index'),
    );

    expect($result['matrix']['X1']['X1'])->toBe(1.0)
        ->and($result['matrix']['X2']['X2'])->toBe(1.0)
        ->and($result['matrix']['Y']['Y'])->toBe(1.0);
});

// ─── OLS Regression ───────────────────────────────────────────────────────────

test('train: n equals number of input rows', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());

    expect($result['n'])->toBe(30);
});

test('train: R-squared is greater than 0.5', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());

    // xlsx shows R² is meaningful for these 3 predictors
    expect($result['r_squared'])->toBeGreaterThan(0.5);
});

test('train: df_regression = 3 and df_residual = n-k-1 = 26', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());

    expect($result['df_regression'])->toBe(3)
        ->and($result['df_residual'])->toBe(26);
});

test('train: equation string contains Y, X1, X2, X3', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());

    expect($result['equation'])->toContain('Y = ')
        ->and($result['equation'])->toContain('X1')
        ->and($result['equation'])->toContain('X2')
        ->and($result['equation'])->toContain('X3');
});

test('train: SS_total = SS_regression + SS_residual', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());

    $recomputed = $result['ss_regression'] + $result['ss_residual'];
    expect(abs($recomputed - $result['ss_total']))->toBeLessThan(1.0); // within 1 unit
});

test('train: residuals sum is approximately zero', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());

    expect(abs(array_sum($result['residuals'])))->toBeLessThan(1.0);
});

test('train: fitted values count equals n', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());

    expect(count($result['fitted']))->toBe(30);
});

test('train: empty result returned when fewer than 5 rows', function () {
    $service = new MlRegressionService;
    $rows = array_slice(xlsxTrainingRows(), 0, 3);
    $result = $service->train($rows);

    expect($result['r_squared'])->toBe(0.0)
        ->and($result['n'])->toBe(3);
});

// ─── Predict ─────────────────────────────────────────────────────────────────

test('predict: returns a numeric result consistent with coefficients', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());
    $coef = $result['coefficients'];

    $predicted = $service->predict(20, 3000, 730, $coef);
    $manual = $coef['b0'] + $coef['b1'] * 20 + $coef['b2'] * 3000 + $coef['b3'] * 730;

    expect(abs($predicted - $manual))->toBeLessThan(0.01);
});

// ─── VIF ─────────────────────────────────────────────────────────────────────

test('VIF values are all less than 10 (no severe multicollinearity)', function () {
    $service = new MlRegressionService;
    $rows = xlsxTrainingRows();
    $vif = $service->vif(
        array_column($rows, 'working_days'),
        array_column($rows, 'production_volume'),
        array_column($rows, 'man_power'),
    );

    expect($vif['x1'])->toBeLessThan(10.0)
        ->and($vif['x2'])->toBeLessThan(10.0)
        ->and($vif['x3'])->toBeLessThan(10.0);
});

// ─── Durbin-Watson ────────────────────────────────────────────────────────────

test('durbin-watson returns a value between 0 and 4', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());
    $dw = $service->durbinWatson($result['residuals']);

    expect($dw['dw'])->toBeGreaterThan(0.0)->toBeLessThan(4.0);
});

test('durbin-watson returns correct table constants dL and dU', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());
    $dw = $service->durbinWatson($result['residuals']);

    expect($dw['dl'])->toBe(1.2138)
        ->and($dw['du'])->toBe(1.6498);
});

// ─── Jarque-Bera ─────────────────────────────────────────────────────────────

test('jarque-bera returns a JB value and a boolean passed field', function () {
    $service = new MlRegressionService;
    $result = $service->train(xlsxTrainingRows());
    $jb = $service->jarqueBera($result['residuals']);

    expect($jb)->toHaveKeys(['jb', 'skewness', 'excess_kurtosis', 'p_value', 'passed', 'decision'])
        ->and($jb['jb'])->toBeGreaterThanOrEqual(0.0)
        ->and($jb['p_value'])->toBeBetween(0.0, 1.0);
});

// ─── Benchmark Ratio ──────────────────────────────────────────────────────────

test('benchmark ratio is a positive float', function () {
    $service = new MlRegressionService;
    $ratio = $service->benchmarkRatio(xlsxTrainingRows());

    expect($ratio)->toBeGreaterThan(0.0);
});

// ─── Statistical distributions ────────────────────────────────────────────────

test('tDistPValue: p-value for t=0 is 1.0', function () {
    $service = new MlRegressionService;
    expect($service->tDistPValue(0, 26))->toBe(1.0);
});

test('tDistPValue: p-value for large |t| is near 0', function () {
    $service = new MlRegressionService;
    expect($service->tDistPValue(10.0, 26))->toBeLessThan(0.001);
});

test('fDistPValue: p-value for F=0 is 1.0', function () {
    $service = new MlRegressionService;
    expect($service->fDistPValue(0, 3, 26))->toBe(1.0);
});

test('chi2PValue: p-value for chi2=0 is 1.0', function () {
    $service = new MlRegressionService;
    expect($service->chi2PValue(0, 2))->toBe(1.0);
});
