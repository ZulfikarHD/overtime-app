<?php

namespace App\Services\Analytics;

/**
 * Multiple Linear Regression (OLS) service for the Overtime Index model.
 *
 * Model: Y = b0 + b1·X1 + b2·X2 + b3·X3
 *   X1 = Jumlah Hari Kerja
 *   X2 = Volume Produksi
 *   X3 = Total Man Power
 *   Y  = Total Index Overtime
 *
 * All computation is pure PHP — no Python or external libraries.
 */
class MlRegressionService
{
    // ─── Durbin-Watson table (n=30, k=3, α=5%) ───────────────────────────────
    private const DW_DL = 1.2138;

    private const DW_DU = 1.6498;

    // Chi-square 95th percentile at df=2 (for Jarque-Bera)
    private const CHI2_DF2_95 = 5.9915;

    /** Indonesian month names */
    private const MONTHS_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Train OLS model from an array of rows.
     *
     * Each row must have: working_days, production_volume, man_power, overtime_index.
     *
     * @param  array<int, array{working_days:int, production_volume:int, man_power:int, overtime_index:int}>  $rows  sorted chronologically
     * @return array{
     *     n: int,
     *     coefficients: array{b0: float, b1: float, b2: float, b3: float},
     *     std_errors: array{b0: float, b1: float, b2: float, b3: float},
     *     t_stats: array{b0: float, b1: float, b2: float, b3: float},
     *     p_values: array{b0: float, b1: float, b2: float, b3: float},
     *     ci95_lower: array{b0: float, b1: float, b2: float, b3: float},
     *     ci95_upper: array{b0: float, b1: float, b2: float, b3: float},
     *     r_squared: float,
     *     adjusted_r_squared: float,
     *     r_multiple: float,
     *     see: float,
     *     mape: float,
     *     ss_regression: float,
     *     ss_residual: float,
     *     ss_total: float,
     *     ms_regression: float,
     *     ms_residual: float,
     *     f_statistic: float,
     *     p_value_f: float,
     *     df_regression: int,
     *     df_residual: int,
     *     equation: string,
     *     residuals: list<float>,
     *     fitted: list<float>,
     * }
     */
    public function train(array $rows): array
    {
        $n = count($rows);
        if ($n < 5) {
            return $this->emptyResult($n);
        }

        $x1 = array_column($rows, 'working_days');
        $x2 = array_column($rows, 'production_volume');
        $x3 = array_column($rows, 'man_power');
        $y = array_column($rows, 'overtime_index');

        // Build design matrix X (n×4): [1, x1, x2, x3]
        $X = [];
        for ($i = 0; $i < $n; $i++) {
            $X[$i] = [1.0, (float) $x1[$i], (float) $x2[$i], (float) $x3[$i]];
        }

        $Y = array_map('floatval', $y);

        // β = (XᵀX)⁻¹ Xᵀy
        $XtX = $this->matMul($this->transpose($X), $X);
        $XtY = $this->matVecMul($this->transpose($X), $Y);
        $XtXinv = $this->invertMatrix4($XtX);

        if ($XtXinv === null) {
            return $this->emptyResult($n);
        }

        $beta = $this->matVecMul($XtXinv, $XtY); // [b0, b1, b2, b3]
        [$b0, $b1, $b2, $b3] = $beta;

        // Fitted values and residuals
        $fitted = [];
        $residuals = [];
        for ($i = 0; $i < $n; $i++) {
            $yhat = $b0 + $b1 * $X[$i][1] + $b2 * $X[$i][2] + $b3 * $X[$i][3];
            $fitted[] = $yhat;
            $residuals[] = $Y[$i] - $yhat;
        }

        $yMean = array_sum($Y) / $n;
        $ssRes = array_sum(array_map(fn ($e) => $e * $e, $residuals));
        $ssTot = array_sum(array_map(fn ($yi) => ($yi - $yMean) ** 2, $Y));
        $ssReg = $ssTot - $ssRes;
        $k = 3; // predictors
        $dfReg = $k;
        $dfRes = $n - $k - 1;

        $msReg = $dfReg > 0 ? $ssReg / $dfReg : 0.0;
        $msRes = $dfRes > 0 ? $ssRes / $dfRes : 0.0;
        $see = $msRes > 0 ? sqrt($msRes) : 0.0;

        $r2 = $ssTot > 0 ? max(0.0, $ssReg / $ssTot) : 0.0;
        $adjR2 = $dfRes > 0 ? 1 - (1 - $r2) * ($n - 1) / $dfRes : 0.0;
        $rMult = sqrt(max(0.0, $r2));

        $fStat = $msRes > 0 ? $msReg / $msRes : 0.0;
        $pValueF = $this->fDistPValue($fStat, $dfReg, $dfRes);

        // MAPE
        $mape = 0.0;
        $mapeCount = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($Y[$i] != 0) {
                $mape += abs($residuals[$i] / $Y[$i]);
                $mapeCount++;
            }
        }
        $mape = $mapeCount > 0 ? ($mape / $mapeCount) * 100 : 0.0;

        // Standard errors of coefficients: SE(β) = sqrt(diag(σ²·(XᵀX)⁻¹))
        $tCrit = $this->tInv(0.025, $dfRes); // two-tailed 95% CI critical value
        $keys = ['b0', 'b1', 'b2', 'b3'];
        $seArr = $tArr = $pArr = $ciLow = $ciHigh = [];
        for ($j = 0; $j < 4; $j++) {
            $variance = $msRes * $XtXinv[$j][$j];
            $se = $variance > 0 ? sqrt($variance) : 0.0;
            $t = $se > 0 ? $beta[$j] / $se : 0.0;
            $p = $this->tDistPValue(abs($t), $dfRes);
            $seArr[$keys[$j]] = round($se, 4);
            $tArr[$keys[$j]] = round($t, 4);
            $pArr[$keys[$j]] = round($p, 6);
            $ciLow[$keys[$j]] = round($beta[$j] - $tCrit * $se, 4);
            $ciHigh[$keys[$j]] = round($beta[$j] + $tCrit * $se, 4);
        }

        $coef = [
            'b0' => round($b0, 4),
            'b1' => round($b1, 4),
            'b2' => round($b2, 4),
            'b3' => round($b3, 4),
        ];

        $equation = sprintf(
            'Y = %s + (%s × X1) + (%s × X2) + (%s × X3)',
            number_format($b0, 2),
            number_format($b1, 2),
            number_format($b2, 2),
            number_format($b3, 2),
        );

        return [
            'n' => $n,
            'coefficients' => $coef,
            'std_errors' => $seArr,
            't_stats' => $tArr,
            'p_values' => $pArr,
            'ci95_lower' => $ciLow,
            'ci95_upper' => $ciHigh,
            'r_squared' => round($r2, 6),
            'adjusted_r_squared' => round($adjR2, 6),
            'r_multiple' => round($rMult, 6),
            'see' => round($see, 4),
            'mape' => round($mape, 4),
            'ss_regression' => round($ssReg, 2),
            'ss_residual' => round($ssRes, 2),
            'ss_total' => round($ssTot, 2),
            'ms_regression' => round($msReg, 2),
            'ms_residual' => round($msRes, 2),
            'f_statistic' => round($fStat, 4),
            'p_value_f' => round($pValueF, 6),
            'df_regression' => $dfReg,
            'df_residual' => $dfRes,
            'equation' => $equation,
            'residuals' => array_map(fn ($v) => round($v, 4), $residuals),
            'fitted' => array_map(fn ($v) => round($v, 4), $fitted),
        ];
    }

    /**
     * Descriptive statistics for a numeric column.
     *
     * @param  list<int|float>  $col
     * @return array{n: int, mean: float, std_dev: float, min: float, max: float, cv: float}
     */
    public function descriptiveStats(array $col): array
    {
        $n = count($col);
        if ($n === 0) {
            return ['n' => 0, 'mean' => 0.0, 'std_dev' => 0.0, 'min' => 0.0, 'max' => 0.0, 'cv' => 0.0];
        }

        $mean = array_sum($col) / $n;
        $ssq = array_sum(array_map(fn ($v) => ((float) $v - $mean) ** 2, $col));
        $std = $n > 1 ? sqrt($ssq / ($n - 1)) : 0.0;
        $cv = $mean != 0 ? ($std / abs($mean)) * 100 : 0.0;

        return [
            'n' => $n,
            'mean' => round($mean, 4),
            'std_dev' => round($std, 4),
            'min' => (float) min($col),
            'max' => (float) max($col),
            'cv' => round($cv, 4),
        ];
    }

    /**
     * Full Pearson correlation matrix and significance tests for all variable pairs.
     *
     * @param  list<int>  $x1
     * @param  list<int>  $x2
     * @param  list<int>  $x3
     * @param  list<int>  $y
     * @return array{
     *     matrix: array<string, array<string, float>>,
     *     significance: array<int, array{
     *         pair: string, r: float, r_squared: float,
     *         t_stat: float, p_value: float, significant: bool, strength: string
     *     }>
     * }
     */
    public function pearsonMatrix(array $x1, array $x2, array $x3, array $y): array
    {
        $vars = ['X1' => $x1, 'X2' => $x2, 'X3' => $x3, 'Y' => $y];
        $keys = array_keys($vars);
        $n = count($x1);
        $df = $n - 2;

        // 4×4 correlation matrix
        $matrix = [];
        foreach ($keys as $k1) {
            foreach ($keys as $k2) {
                $matrix[$k1][$k2] = round($this->pearsonR($vars[$k1], $vars[$k2]), 4);
            }
        }

        // Significance tests for each X vs Y pair (+ inter-predictor pairs)
        $pairs = [
            ['X1', 'Y', 'X1 (Hari Kerja) vs Y (Index Overtime)'],
            ['X2', 'Y', 'X2 (Volume Produksi) vs Y (Index Overtime)'],
            ['X3', 'Y', 'X3 (Man Power) vs Y (Index Overtime)'],
            ['X1', 'X2', 'X1 vs X2 (multikolinearitas)'],
            ['X1', 'X3', 'X1 vs X3 (multikolinearitas)'],
            ['X2', 'X3', 'X2 vs X3 (multikolinearitas)'],
        ];

        $sig = [];
        foreach ($pairs as $idx => [$a, $b, $label]) {
            $r = $matrix[$a][$b];
            $r2 = $r * $r;
            $t = ($df > 0 && abs($r) < 1.0)
                ? $r * sqrt($df) / sqrt(max(1e-12, 1 - $r2))
                : 0.0;
            $p = $this->tDistPValue(abs($t), $df);

            $strength = $this->correlationStrength(abs($r), $r);

            $sig[] = [
                'pair' => $label,
                'var_a' => $a,
                'var_b' => $b,
                'r' => $r,
                'r_squared' => round($r2, 4),
                't_stat' => round($t, 4),
                'p_value' => round($p, 6),
                'significant' => $p < 0.05,
                'strength' => $strength,
            ];
        }

        return ['matrix' => $matrix, 'significance' => $sig];
    }

    /**
     * Variance Inflation Factor (VIF) for each predictor.
     * VIF_j = 1 / (1 - R²_j) where R²_j is from regressing Xj on the other two.
     *
     * @param  list<int>  $x1
     * @param  list<int>  $x2
     * @param  list<int>  $x3
     * @return array{x1: float, x2: float, x3: float}
     */
    public function vif(array $x1, array $x2, array $x3): array
    {
        $vifX1 = $this->computeVif($x1, $x2, $x3);
        $vifX2 = $this->computeVif($x2, $x1, $x3);
        $vifX3 = $this->computeVif($x3, $x1, $x2);

        return [
            'x1' => round($vifX1, 4),
            'x2' => round($vifX2, 4),
            'x3' => round($vifX3, 4),
        ];
    }

    /**
     * Durbin-Watson autocorrelation test.
     *
     * @param  list<float>  $residuals
     * @return array{dw: float, dl: float, du: float, decision: string, r_lag1: float}
     */
    public function durbinWatson(array $residuals): array
    {
        $n = count($residuals);
        if ($n < 3) {
            return ['dw' => 0.0, 'dl' => self::DW_DL, 'du' => self::DW_DU, 'decision' => 'Tidak cukup data', 'r_lag1' => 0.0];
        }

        $sumDiff2 = 0.0;
        for ($i = 1; $i < $n; $i++) {
            $sumDiff2 += ($residuals[$i] - $residuals[$i - 1]) ** 2;
        }
        $sumE2 = array_sum(array_map(fn ($e) => $e * $e, $residuals));
        $dw = $sumE2 > 0 ? $sumDiff2 / $sumE2 : 0.0;

        // Lag-1 autocorrelation
        $rLag = $this->pearsonR(
            array_slice($residuals, 0, $n - 1),
            array_slice($residuals, 1),
        );

        $dl = self::DW_DL;
        $du = self::DW_DU;

        if ($dw < $dl) {
            $decision = 'Ada autokorelasi positif (TIDAK TERPENUHI)';
        } elseif ($dw < $du) {
            $decision = 'Daerah ragu-ragu (inconclusive)';
        } elseif ($dw <= 4 - $du) {
            $decision = 'Tidak ada autokorelasi (TERPENUHI)';
        } elseif ($dw <= 4 - $dl) {
            $decision = 'Daerah ragu-ragu (inconclusive)';
        } else {
            $decision = 'Ada autokorelasi negatif (TIDAK TERPENUHI)';
        }

        return [
            'dw' => round($dw, 4),
            'dl' => $dl,
            'du' => $du,
            'decision' => $decision,
            'r_lag1' => round($rLag, 4),
        ];
    }

    /**
     * Jarque-Bera normality test on residuals.
     * JB = n/6 × (S² + K²/4)  where S=skewness, K=excess kurtosis.
     *
     * @param  list<float>  $residuals
     * @return array{jb: float, skewness: float, excess_kurtosis: float, p_value: float, passed: bool, decision: string}
     */
    public function jarqueBera(array $residuals): array
    {
        $n = count($residuals);
        if ($n < 4) {
            return ['jb' => 0.0, 'skewness' => 0.0, 'excess_kurtosis' => 0.0, 'p_value' => 1.0, 'passed' => true, 'decision' => 'Tidak cukup data'];
        }

        $mean = array_sum($residuals) / $n;
        $m2 = array_sum(array_map(fn ($e) => ($e - $mean) ** 2, $residuals)) / $n;
        $m3 = array_sum(array_map(fn ($e) => ($e - $mean) ** 3, $residuals)) / $n;
        $m4 = array_sum(array_map(fn ($e) => ($e - $mean) ** 4, $residuals)) / $n;

        $std = $m2 > 0 ? sqrt($m2) : 0.0;

        $skewness = $std > 0 ? $m3 / ($std ** 3) : 0.0;
        $kurtosis = $std > 0 ? $m4 / ($m2 ** 2) - 3 : 0.0; // excess kurtosis

        // Sample-corrected JB (matches Excel SKEW/KURT behavior)
        $skewSample = $n > 2
            ? ($skewness * sqrt($n * ($n - 1)) / ($n - 2))
            : $skewness;
        $kurtSample = $n > 3
            ? (($n + 1) * $kurtosis * ($n - 1) / (($n - 2) * ($n - 3)) + 6 * ($n - 1) / (($n - 2) * ($n - 3)))
            : $kurtosis;

        $jb = ($n / 6.0) * ($skewSample ** 2 + $kurtSample ** 2 / 4.0);
        $pValue = $this->chi2PValue($jb, 2);
        $passed = $pValue > 0.05;

        return [
            'jb' => round($jb, 4),
            'skewness' => round($skewSample, 4),
            'excess_kurtosis' => round($kurtSample, 4),
            'p_value' => round($pValue, 6),
            'passed' => $passed,
            'decision' => $passed
                ? 'Residual berdistribusi normal (TERPENUHI)'
                : 'Residual tidak normal (TIDAK TERPENUHI)',
        ];
    }

    /**
     * Glejser heteroskedasticity test: regress |e| on X1, X2, X3.
     * Returns per-variable significance; any significant variable → heteroskedasticity.
     *
     * @param  list<float>  $absResiduals
     * @param  list<int>  $x1
     * @param  list<int>  $x2
     * @param  list<int>  $x3
     * @param  int  $dfRes  df residual from main model
     * @return array{
     *     intercept: array{coef: float, t: float, p_value: float, significant: bool},
     *     x1: array{coef: float, t: float, p_value: float, significant: bool},
     *     x2: array{coef: float, t: float, p_value: float, significant: bool},
     *     x3: array{coef: float, t: float, p_value: float, significant: bool},
     *     passed: bool,
     *     decision: string,
     * }
     */
    public function glejser(array $absResiduals, array $x1, array $x2, array $x3, int $dfRes): array
    {
        $n = count($absResiduals);
        $X = [];
        for ($i = 0; $i < $n; $i++) {
            $X[$i] = [1.0, (float) $x1[$i], (float) $x2[$i], (float) $x3[$i]];
        }

        $Y = array_map('floatval', $absResiduals);
        $XtX = $this->matMul($this->transpose($X), $X);
        $XtY = $this->matVecMul($this->transpose($X), $Y);
        $XtXinv = $this->invertMatrix4($XtX);

        if ($XtXinv === null) {
            $empty = ['coef' => 0.0, 't' => 0.0, 'p_value' => 1.0, 'significant' => false];

            return [
                'intercept' => $empty, 'x1' => $empty, 'x2' => $empty, 'x3' => $empty,
                'passed' => true, 'decision' => 'Tidak dapat dihitung',
            ];
        }

        $beta = $this->matVecMul($XtXinv, $Y);

        $yHat = [];
        for ($i = 0; $i < $n; $i++) {
            $yHat[] = $beta[0] + $beta[1] * $X[$i][1] + $beta[2] * $X[$i][2] + $beta[3] * $X[$i][3];
        }
        $res2 = array_map(fn ($j) => ($Y[$j] - $yHat[$j]) ** 2, range(0, $n - 1));
        $msRes = $dfRes > 0 ? array_sum($res2) / $dfRes : 0.0;

        $keys = ['intercept', 'x1', 'x2', 'x3'];
        $result = [];
        foreach ($keys as $j => $key) {
            $se = $msRes * $XtXinv[$j][$j] > 0 ? sqrt($msRes * $XtXinv[$j][$j]) : 0.0;
            $t = $se > 0 ? $beta[$j] / $se : 0.0;
            $p = $this->tDistPValue(abs($t), $dfRes);
            $sig = $p < 0.05;

            $result[$key] = ['coef' => round($beta[$j], 4), 't' => round($t, 4), 'p_value' => round($p, 6), 'significant' => $sig];
        }

        // Passed if no predictor (x1/x2/x3) is significant
        $passed = ! $result['x1']['significant'] && ! $result['x2']['significant'] && ! $result['x3']['significant'];

        $result['passed'] = $passed;
        $result['decision'] = $passed
            ? 'Asumsi homoskedastisitas terpenuhi (semua P-Value > 0,05)'
            : 'Ada indikasi heteroskedastisitas (minimal satu variabel signifikan)';

        return $result;
    }

    /**
     * Predict Y for a single observation using model coefficients.
     *
     * @param  array{b0: float, b1: float, b2: float, b3: float}  $coef
     */
    public function predict(float $x1, float $x2, float $x3, array $coef): float
    {
        return $coef['b0'] + $coef['b1'] * $x1 + $coef['b2'] * $x2 + $coef['b3'] * $x3;
    }

    /**
     * Compute the Q1 OT/Volume ratio benchmark from training data.
     * IndexIdeal Benchmark for a forecast row = benchmarkRatio × production_volume.
     *
     * @param  array<int, array{overtime_index:int, production_volume:int}>  $trainingRows
     */
    public function benchmarkRatio(array $trainingRows): float
    {
        $ratios = [];
        foreach ($trainingRows as $row) {
            if ($row['production_volume'] > 0) {
                $ratios[] = $row['overtime_index'] / $row['production_volume'];
            }
        }

        if (count($ratios) === 0) {
            return 0.0;
        }

        sort($ratios);
        $cnt = count($ratios);
        // Use Excel QUARTILE.INC (identical to Excel's default QUARTILE() function):
        // pos = 1 + (n-1) × 0.25
        $pos = 1.0 + ($cnt - 1) * 0.25;
        $low = max(1, min((int) floor($pos), $cnt));
        $high = max(1, min((int) ceil($pos), $cnt));
        $frac = $pos - floor($pos);

        return $ratios[$low - 1] + $frac * ($ratios[$high - 1] - $ratios[$low - 1]);
    }

    /**
     * Convert month number to Indonesian name.
     */
    public function monthName(int $month): string
    {
        return self::MONTHS_ID[$month] ?? (string) $month;
    }

    // ─── Statistical distribution approximations ──────────────────────────────

    /**
     * Two-tailed p-value for a t-statistic with given degrees of freedom.
     * Uses a rational approximation of the regularized incomplete beta function.
     */
    public function tDistPValue(float $t, int $df): float
    {
        if ($df <= 0) {
            return 1.0;
        }
        $t = abs($t);
        if ($t === 0.0) {
            return 1.0;
        }

        // Use Normal approximation for large df
        if ($df >= 200) {
            return 2 * (1 - $this->normalCdf($t));
        }

        // Regularized incomplete beta: p = Ix(df/2, 1/2) where x = df/(df+t²)
        $x = $df / ($df + $t * $t);
        $p = $this->regularizedIncompleteBeta($x, $df / 2.0, 0.5);

        return min(1.0, max(0.0, $p));
    }

    /**
     * Two-tailed critical t value (inverse CDF approximation).
     * alpha is the one-tail probability (e.g. 0.025 for 95% CI).
     */
    public function tInv(float $alpha, int $df): float
    {
        // Newton-Raphson inversion of the t CDF
        $t = 1.96; // starting point
        for ($i = 0; $i < 50; $i++) {
            $p = $this->tDistPValue($t, $df) / 2; // one-tail
            $pdf = $this->tPdf($t, $df);
            if ($pdf < 1e-15) {
                break;
            }
            $delta = ($p - $alpha) / $pdf;
            $t -= $delta;
            if (abs($delta) < 1e-8) {
                break;
            }
        }

        return abs($t);
    }

    /**
     * P-value for F-statistic with df1 numerator and df2 denominator df.
     */
    public function fDistPValue(float $f, int $df1, int $df2): float
    {
        if ($f <= 0 || $df1 <= 0 || $df2 <= 0) {
            return 1.0;
        }

        $x = ($df1 * $f) / ($df1 * $f + $df2);
        // P(F > f) = Ix(df1/2, df2/2) — upper tail
        $upper = $this->regularizedIncompleteBeta($x, $df1 / 2.0, $df2 / 2.0);

        return min(1.0, max(0.0, $upper));
    }

    /**
     * Chi-squared p-value (upper tail) via Gamma function approximation.
     */
    public function chi2PValue(float $x, int $df): float
    {
        if ($x <= 0) {
            return 1.0;
        }

        return $this->regularizedGammaUpper($df / 2.0, $x / 2.0);
    }

    // ─── Matrix helpers ──────────────────────────────────────────────────────

    /**
     * @param  array<int, array<int, float>>  $A
     * @param  array<int, array<int, float>>  $B
     * @return array<int, array<int, float>>
     */
    private function matMul(array $A, array $B): array
    {
        $rows = count($A);
        $cols = count($B[0]);
        $inner = count($B);
        $C = [];
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $s = 0.0;
                for ($k = 0; $k < $inner; $k++) {
                    $s += $A[$i][$k] * $B[$k][$j];
                }
                $C[$i][$j] = $s;
            }
        }

        return $C;
    }

    /**
     * @param  array<int, array<int, float>>  $A
     * @return array<int, array<int, float>>
     */
    private function transpose(array $A): array
    {
        $rows = count($A);
        $cols = count($A[0]);
        $T = [];
        for ($j = 0; $j < $cols; $j++) {
            for ($i = 0; $i < $rows; $i++) {
                $T[$j][$i] = $A[$i][$j];
            }
        }

        return $T;
    }

    /**
     * @param  array<int, array<int, float>>  $A
     * @param  array<int, float>  $v
     * @return array<int, float>
     */
    private function matVecMul(array $A, array $v): array
    {
        $result = [];
        foreach ($A as $row) {
            $s = 0.0;
            foreach ($row as $j => $a) {
                $s += $a * $v[$j];
            }
            $result[] = $s;
        }

        return $result;
    }

    /**
     * Invert a 4×4 matrix via Gauss-Jordan elimination.
     *
     * @param  array<int, array<int, float>>  $M
     * @return array<int, array<int, float>>|null null if singular
     */
    private function invertMatrix4(array $M): ?array
    {
        $n = 4;
        // Augment with identity
        $A = [];
        for ($i = 0; $i < $n; $i++) {
            $A[$i] = $M[$i];
            for ($j = 0; $j < $n; $j++) {
                $A[$i][$n + $j] = ($i === $j) ? 1.0 : 0.0;
            }
        }

        for ($col = 0; $col < $n; $col++) {
            // Partial pivot
            $maxRow = $col;
            for ($r = $col + 1; $r < $n; $r++) {
                if (abs($A[$r][$col]) > abs($A[$maxRow][$col])) {
                    $maxRow = $r;
                }
            }
            [$A[$col], $A[$maxRow]] = [$A[$maxRow], $A[$col]];

            $pivot = $A[$col][$col];
            if (abs($pivot) < 1e-12) {
                return null; // Singular
            }

            for ($j = $col; $j < 2 * $n; $j++) {
                $A[$col][$j] /= $pivot;
            }

            for ($r = 0; $r < $n; $r++) {
                if ($r === $col) {
                    continue;
                }
                $factor = $A[$r][$col];
                for ($j = $col; $j < 2 * $n; $j++) {
                    $A[$r][$j] -= $factor * $A[$col][$j];
                }
            }
        }

        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $inv[$i][$j] = $A[$i][$n + $j];
            }
        }

        return $inv;
    }

    // ─── Statistical helpers ─────────────────────────────────────────────────

    /**
     * @param  list<float|int>  $x
     * @param  list<float|int>  $y
     */
    private function pearsonR(array $x, array $y): float
    {
        $n = count($x);
        if ($n < 2 || $n !== count($y)) {
            return 0.0;
        }

        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $num = $dx2 = $dy2 = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = (float) $x[$i] - $mx;
            $dy = (float) $y[$i] - $my;
            $num += $dx * $dy;
            $dx2 += $dx * $dx;
            $dy2 += $dy * $dy;
        }

        $denom = sqrt($dx2 * $dy2);

        return $denom > 0 ? max(-1.0, min(1.0, $num / $denom)) : 0.0;
    }

    /**
     * OLS R² for regressing $dep on $ind1 and $ind2 (used for VIF).
     *
     * @param  list<int>  $dep
     * @param  list<int>  $ind1
     * @param  list<int>  $ind2
     */
    private function computeVif(array $dep, array $ind1, array $ind2): float
    {
        $n = count($dep);
        $X = [];
        for ($i = 0; $i < $n; $i++) {
            $X[$i] = [1.0, (float) $ind1[$i], (float) $ind2[$i]];
        }

        $Y = array_map('floatval', $dep);
        $XtX = $this->matMul($this->transpose($X), $X);
        $XtY = $this->matVecMul($this->transpose($X), $Y);
        $inv = $this->invertMatrix3($XtX);

        if ($inv === null) {
            return 1.0;
        }

        $beta = $this->matVecMul($inv, $XtY);
        $yMean = array_sum($Y) / $n;
        $ssTot = array_sum(array_map(fn ($yi) => ($yi - $yMean) ** 2, $Y));
        $ssRes = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $yhat = $beta[0] + $beta[1] * $X[$i][1] + $beta[2] * $X[$i][2];
            $ssRes += ($Y[$i] - $yhat) ** 2;
        }

        $r2 = $ssTot > 0 ? max(0.0, 1 - $ssRes / $ssTot) : 0.0;
        $tol = max(1e-12, 1 - $r2);

        return 1 / $tol;
    }

    /**
     * Invert a 3×3 matrix via Gauss-Jordan.
     *
     * @param  array<int, array<int, float>>  $M
     * @return array<int, array<int, float>>|null
     */
    private function invertMatrix3(array $M): ?array
    {
        $n = 3;
        $A = [];
        for ($i = 0; $i < $n; $i++) {
            $A[$i] = $M[$i];
            for ($j = 0; $j < $n; $j++) {
                $A[$i][$n + $j] = ($i === $j) ? 1.0 : 0.0;
            }
        }

        for ($col = 0; $col < $n; $col++) {
            $maxRow = $col;
            for ($r = $col + 1; $r < $n; $r++) {
                if (abs($A[$r][$col]) > abs($A[$maxRow][$col])) {
                    $maxRow = $r;
                }
            }
            [$A[$col], $A[$maxRow]] = [$A[$maxRow], $A[$col]];
            $pivot = $A[$col][$col];
            if (abs($pivot) < 1e-12) {
                return null;
            }
            for ($j = $col; $j < 2 * $n; $j++) {
                $A[$col][$j] /= $pivot;
            }
            for ($r = 0; $r < $n; $r++) {
                if ($r === $col) {
                    continue;
                }
                $f = $A[$r][$col];
                for ($j = $col; $j < 2 * $n; $j++) {
                    $A[$r][$j] -= $f * $A[$col][$j];
                }
            }
        }

        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $inv[$i][$j] = $A[$i][$n + $j];
            }
        }

        return $inv;
    }

    /**
     * Regularized incomplete beta function I_x(a, b) via continued fraction.
     */
    private function regularizedIncompleteBeta(float $x, float $a, float $b): float
    {
        if ($x <= 0.0) {
            return 1.0;
        }
        if ($x >= 1.0) {
            return 0.0;
        }

        // Use symmetry for better convergence
        if ($x > ($a + 1) / ($a + $b + 2)) {
            return 1.0 - $this->regularizedIncompleteBeta(1 - $x, $b, $a);
        }

        $lbeta = $this->logBeta($a, $b);
        $front = exp(log($x) * $a + log(1 - $x) * $b - $lbeta) / $a;

        return $front * $this->betaContinuedFraction($x, $a, $b);
    }

    private function betaContinuedFraction(float $x, float $a, float $b): float
    {
        $maxIter = 200;
        $eps = 3e-7;
        $fpmin = 1e-30;

        $qab = $a + $b;
        $qap = $a + 1;
        $qam = $a - 1;
        $c = 1.0;
        $d = 1.0 - $qab * $x / $qap;
        if (abs($d) < $fpmin) {
            $d = $fpmin;
        }
        $d = 1.0 / $d;
        $h = $d;

        for ($m = 1; $m <= $maxIter; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1.0 / $d;
            $h *= $d * $c;
            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1.0 + $aa * $d;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = 1.0 + $aa / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;
            if (abs($del - 1.0) < $eps) {
                break;
            }
        }

        return $h;
    }

    private function logBeta(float $a, float $b): float
    {
        return $this->logGamma($a) + $this->logGamma($b) - $this->logGamma($a + $b);
    }

    /**
     * Log Gamma via Lanczos approximation.
     */
    private function logGamma(float $z): float
    {
        $g = 7;
        $c = [
            0.99999999999980993, 676.5203681218851, -1259.1392167224028,
            771.32342877765313, -176.61502916214059, 12.507343278686905,
            -0.13857109526572012, 9.9843695780195716e-6, 1.5056327351493116e-7,
        ];

        if ($z < 0.5) {
            return log(M_PI / sin(M_PI * $z)) - $this->logGamma(1 - $z);
        }

        $z -= 1;
        $x = $c[0];
        for ($i = 1; $i < $g + 2; $i++) {
            $x += $c[$i] / ($z + $i);
        }
        $t = $z + $g + 0.5;

        return 0.5 * log(2 * M_PI) + ($z + 0.5) * log($t) - $t + log($x);
    }

    /**
     * Regularized upper incomplete gamma function Q(a, x) = 1 - P(a, x).
     */
    private function regularizedGammaUpper(float $a, float $x): float
    {
        if ($x <= 0) {
            return 1.0;
        }
        if ($x < $a + 1) {
            return 1.0 - $this->regularizedGammaLower($a, $x);
        }

        // Continued fraction representation
        $fpmin = 1e-30;
        $b = $x + 1.0 - $a;
        $c = 1.0 / $fpmin;
        $d = 1.0 / $b;
        $h = $d;

        for ($i = 1; $i <= 100; $i++) {
            $an = -$i * ($i - $a);
            $b += 2.0;
            $d = $an * $d + $b;
            if (abs($d) < $fpmin) {
                $d = $fpmin;
            }
            $c = $b + $an / $c;
            if (abs($c) < $fpmin) {
                $c = $fpmin;
            }
            $d = 1.0 / $d;
            $del = $d * $c;
            $h *= $del;
            if (abs($del - 1.0) < 3e-7) {
                break;
            }
        }

        return exp(-$x + $a * log($x) - $this->logGamma($a)) * $h;
    }

    private function regularizedGammaLower(float $a, float $x): float
    {
        $sum = 1.0 / $a;
        $del = $sum;
        $ap = $a;

        for ($n = 1; $n <= 200; $n++) {
            $ap++;
            $del *= $x / $ap;
            $sum += $del;
            if (abs($del) < abs($sum) * 3e-7) {
                break;
            }
        }

        return $sum * exp(-$x + $a * log($x) - $this->logGamma($a));
    }

    private function normalCdf(float $z): float
    {
        $t = 1 / (1 + 0.2316419 * abs($z));
        $poly = $t * (0.319381530 + $t * (-0.356563782 + $t * (1.781477937 + $t * (-1.821255978 + $t * 1.330274429))));
        $pdf = exp(-0.5 * $z * $z) / sqrt(2 * M_PI);
        $cdf = 1 - $pdf * $poly;

        return $z >= 0 ? $cdf : 1 - $cdf;
    }

    private function tPdf(float $t, int $df): float
    {
        $lnum = $this->logGamma(($df + 1) / 2.0);
        $lden = 0.5 * log($df * M_PI) + $this->logGamma($df / 2.0);
        $body = -(($df + 1) / 2.0) * log(1 + $t * $t / $df);

        return exp($lnum - $lden + $body);
    }

    private function correlationStrength(float $absR, float $r): string
    {
        $dir = $r >= 0 ? 'Positif' : 'Negatif';
        if ($absR >= 0.8) {
            $str = 'Sangat Kuat';
        } elseif ($absR >= 0.6) {
            $str = 'Kuat';
        } elseif ($absR >= 0.4) {
            $str = 'Sedang';
        } elseif ($absR >= 0.2) {
            $str = 'Lemah';
        } else {
            $str = 'Sangat Lemah';
        }

        return "{$str} / {$dir}";
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(int $n): array
    {
        $zero = ['b0' => 0.0, 'b1' => 0.0, 'b2' => 0.0, 'b3' => 0.0];

        return [
            'n' => $n, 'coefficients' => $zero, 'std_errors' => $zero,
            't_stats' => $zero, 'p_values' => $zero, 'ci95_lower' => $zero, 'ci95_upper' => $zero,
            'r_squared' => 0.0, 'adjusted_r_squared' => 0.0, 'r_multiple' => 0.0,
            'see' => 0.0, 'mape' => 0.0, 'ss_regression' => 0.0, 'ss_residual' => 0.0,
            'ss_total' => 0.0, 'ms_regression' => 0.0, 'ms_residual' => 0.0,
            'f_statistic' => 0.0, 'p_value_f' => 1.0, 'df_regression' => 3, 'df_residual' => $n - 4,
            'equation' => 'Y = ? (data tidak cukup)', 'residuals' => [], 'fitted' => [],
        ];
    }
}
