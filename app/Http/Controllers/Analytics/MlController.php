<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\MlForecastInput;
use App\Models\MlTrainingData;
use App\Models\User;
use App\Services\Analytics\MlRegressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MlController extends Controller
{
    public function __construct(
        private readonly MlRegressionService $mlService,
    ) {}

    // ─── Data Training ───────────────────────────────────────────────────────

    /**
     * Display the training data page with all monthly observations.
     */
    public function trainingData(): Response
    {
        $rows = MlTrainingData::orderBy('year')->orderBy('month')->get();

        return Inertia::render('Analytics/Ml/DataTraining', [
            'rows' => $rows->map(fn (MlTrainingData $r) => [
                'id' => $r->id,
                'year' => $r->year,
                'month' => $r->month,
                'month_name' => $this->mlService->monthName($r->month),
                'period_label' => $this->mlService->monthName($r->month).' '.$r->year,
                'working_days' => $r->working_days,
                'production_volume' => $r->production_volume,
                'man_power' => $r->man_power,
                'overtime_index' => $r->overtime_index,
            ])->values()->all(),
        ]);
    }

    /**
     * Store a new training data row.
     */
    public function storeTraining(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12', Rule::unique('ml_training_data')->where('year', $request->input('year'))],
            'working_days' => ['required', 'integer', 'min:1', 'max:31'],
            'production_volume' => ['required', 'integer', 'min:1'],
            'man_power' => ['required', 'integer', 'min:1'],
            'overtime_index' => ['required', 'integer', 'min:0'],
        ]);

        MlTrainingData::create($data);

        return back()->with('success', 'Data training berhasil disimpan.');
    }

    /**
     * Update an existing training data row.
     */
    public function updateTraining(Request $request, MlTrainingData $mlTrainingData): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'working_days' => ['required', 'integer', 'min:1', 'max:31'],
            'production_volume' => ['required', 'integer', 'min:1'],
            'man_power' => ['required', 'integer', 'min:1'],
            'overtime_index' => ['required', 'integer', 'min:0'],
        ]);

        $mlTrainingData->update($data);

        return back()->with('success', 'Data training berhasil diperbarui.');
    }

    /**
     * Delete a training data row.
     */
    public function destroyTraining(Request $request, MlTrainingData $mlTrainingData): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $mlTrainingData->delete();

        return back()->with('success', 'Data training berhasil dihapus.');
    }

    // ─── Hasil Analisis ──────────────────────────────────────────────────────

    /**
     * Display the statistical analysis results page.
     */
    public function analysis(): Response
    {
        $rows = MlTrainingData::orderBy('year')->orderBy('month')->get();

        if ($rows->isEmpty()) {
            return Inertia::render('Analytics/Ml/HasilAnalisis', [
                'has_training_data' => false,
                'descriptive' => [],
                'pearson' => [],
                'regression' => [],
                'assumptions' => [],
            ]);
        }

        $plain = $rows->map(fn (MlTrainingData $r) => [
            'working_days' => $r->working_days,
            'production_volume' => $r->production_volume,
            'man_power' => $r->man_power,
            'overtime_index' => $r->overtime_index,
        ])->all();

        $x1 = array_column($plain, 'working_days');
        $x2 = array_column($plain, 'production_volume');
        $x3 = array_column($plain, 'man_power');
        $y = array_column($plain, 'overtime_index');

        // Descriptive statistics
        $descriptive = [
            ['variable' => 'X1 - Jumlah Hari Kerja', 'key' => 'x1', ...$this->mlService->descriptiveStats($x1)],
            ['variable' => 'X2 - Volume Produksi', 'key' => 'x2', ...$this->mlService->descriptiveStats($x2)],
            ['variable' => 'X3 - Total Man Power', 'key' => 'x3', ...$this->mlService->descriptiveStats($x3)],
            ['variable' => 'Y - Total Index Overtime', 'key' => 'y', ...$this->mlService->descriptiveStats($y)],
        ];

        // Pearson correlation
        $pearson = $this->mlService->pearsonMatrix($x1, $x2, $x3, $y);

        // OLS Regression
        $regression = $this->mlService->train($plain);

        // Classical assumption tests
        $residuals = $regression['residuals'];
        $absResiduals = array_map('abs', $residuals);
        $dfRes = $regression['df_residual'];

        $jb = $this->mlService->jarqueBera($residuals);
        $dw = $this->mlService->durbinWatson($residuals);
        $glejser = $this->mlService->glejser($absResiduals, $x1, $x2, $x3, $dfRes);
        $vif = $this->mlService->vif($x1, $x2, $x3);

        $assumptions = [
            'jarque_bera' => $jb,
            'durbin_watson' => $dw,
            'glejser' => $glejser,
            'vif' => [
                'x1' => ['value' => $vif['x1'], 'passed' => $vif['x1'] < 10, 'label' => 'X1 - Hari Kerja'],
                'x2' => ['value' => $vif['x2'], 'passed' => $vif['x2'] < 10, 'label' => 'X2 - Volume Produksi'],
                'x3' => ['value' => $vif['x3'], 'passed' => $vif['x3'] < 10, 'label' => 'X3 - Man Power'],
            ],
            'f_linearity' => [
                'f_stat' => $regression['f_statistic'],
                'p_value' => $regression['p_value_f'],
                'passed' => $regression['p_value_f'] < 0.05,
                'decision' => $regression['p_value_f'] < 0.05
                    ? 'Hubungan linier terbukti (TERPENUHI)'
                    : 'Hubungan linier tidak terbukti (TIDAK TERPENUHI)',
            ],
        ];

        // Chart data for Regression tab
        $periodLabels = $rows->map(
            fn (MlTrainingData $r) => $this->mlService->monthName($r->month).' '.$r->year
        )->values()->all();

        // Scatter: Actual vs Fitted pairs (industry-standard regression diagnostic)
        $scatterPairs = array_map(
            fn ($a, $f) => ['x' => round((float) $f, 2), 'y' => round((float) $a, 2)],
            $y,
            $regression['fitted']
        );

        // Q-Q plot: sorted standardized residuals vs theoretical normal quantiles
        $res = $regression['residuals'];
        $nRes = count($res);
        $se = $regression['see'] ?? 1;
        $stdRes = $se > 0
            ? array_map(fn ($r) => $r / $se, $res)
            : $res;
        $sortedStdRes = $stdRes;
        sort($sortedStdRes);
        $qqPairs = array_map(function ($i) use ($sortedStdRes, $nRes) {
            $p = ($i + 1 - 0.375) / ($nRes + 0.25);
            $p = max(0.0001, min(0.9999, $p));
            if ($p <= 0.5) {
                $t = sqrt(-2 * log($p));
                $z = -(2.515517 + 0.802853 * $t + 0.010328 * $t ** 2)
                    / (1 + 1.432788 * $t + 0.189269 * $t ** 2 + 0.001308 * $t ** 3);
            } else {
                $t = sqrt(-2 * log(1 - $p));
                $z = (2.515517 + 0.802853 * $t + 0.010328 * $t ** 2)
                    / (1 + 1.432788 * $t + 0.189269 * $t ** 2 + 0.001308 * $t ** 3);
            }

            return ['x' => round($z, 4), 'y' => round($sortedStdRes[$i], 4)];
        }, range(0, $nRes - 1));

        $chartData = [
            'labels' => $periodLabels,
            'actual' => array_values($y),
            'fitted' => array_values($regression['fitted']),
            'residuals' => array_values($regression['residuals']),
            'scatter_pairs' => array_values($scatterPairs),
            'qq_pairs' => array_values($qqPairs),
        ];

        return Inertia::render('Analytics/Ml/HasilAnalisis', [
            'has_training_data' => true,
            'descriptive' => $descriptive,
            'pearson' => $pearson,
            'regression' => $regression,
            'assumptions' => $assumptions,
            'n' => $rows->count(),
            'chart_data' => $chartData,
        ]);
    }

    // ─── Analisis Forecasting ─────────────────────────────────────────────────

    /**
     * Display forecasting page — shows a full 12-month view for the requested year.
     * Months with training data appear as "historical" (actual Y known).
     * Months with forecast inputs appear as "forecast" (planned X, predicted Y).
     */
    public function forecasting(Request $request): Response
    {
        $viewYear = (int) $request->query('year', 2026);

        $trainingRows = MlTrainingData::orderBy('year')->orderBy('month')->get();

        if ($trainingRows->isEmpty()) {
            return Inertia::render('Analytics/Ml/Forecasting', [
                'has_training_data' => false,
                'equation' => null,
                'benchmark_ratio' => 0,
                'forecast_rows' => [],
                'view_year' => $viewYear,
            ]);
        }

        $plain = $trainingRows->map(fn (MlTrainingData $r) => [
            'working_days' => $r->working_days,
            'production_volume' => $r->production_volume,
            'man_power' => $r->man_power,
            'overtime_index' => $r->overtime_index,
        ])->all();

        $regression = $this->mlService->train($plain);
        $coef = $regression['coefficients'];
        $benchmarkRatio = $this->mlService->benchmarkRatio($plain);

        // Pull year-specific training months (historical actuals for the view year)
        $yearTraining = MlTrainingData::where('year', $viewYear)->orderBy('month')->get()
            ->keyBy('month');

        // Pull forecast inputs for the view year
        $yearForecast = MlForecastInput::where('year', $viewYear)->orderBy('month')->get()
            ->keyBy('month');

        // Build all 12 months in order
        $rows = [];
        for ($month = 1; $month <= 12; $month++) {
            if ($yearTraining->has($month)) {
                // Historical month — actual data exists
                $tr = $yearTraining[$month];
                $indexIdeal = $this->mlService->predict(
                    (float) $tr->working_days,
                    (float) $tr->production_volume,
                    (float) $tr->man_power,
                    $coef,
                );
                $benchmark = $benchmarkRatio * $tr->production_volume;
                $aktual = (float) $tr->overtime_index;
                $selisih = $indexIdeal - $benchmark;

                $rows[] = [
                    'id' => null,
                    'source' => 'training',
                    'year' => $viewYear,
                    'month' => $month,
                    'month_name' => $this->mlService->monthName($month),
                    'period_label' => $this->mlService->monthName($month).' '.$viewYear,
                    'working_days' => $tr->working_days,
                    'production_volume' => $tr->production_volume,
                    'man_power' => $tr->man_power,
                    'actual_overtime_index' => $aktual,
                    'index_ideal' => round($indexIdeal, 2),
                    'index_benchmark' => round($benchmark, 2),
                    'selisih_ideal_benchmark' => round($selisih, 2),
                    'deviasi_pct' => $indexIdeal > 0
                        ? round(($aktual - $indexIdeal) / $indexIdeal * 100, 2)
                        : null,
                    'status' => null, // historical rows don't show Over/Under
                ];
            } elseif ($yearForecast->has($month)) {
                // Forecast month — planned X, no actual Y yet (or Y filled in later)
                $fr = $yearForecast[$month];
                $indexIdeal = $this->mlService->predict(
                    (float) $fr->working_days,
                    (float) $fr->production_volume,
                    (float) $fr->man_power,
                    $coef,
                );
                $benchmark = $benchmarkRatio * $fr->production_volume;
                $aktual = $fr->actual_overtime_index;
                $selisih = $indexIdeal - $benchmark;
                $deviasi = ($aktual !== null && $indexIdeal > 0)
                    ? round(($aktual - $indexIdeal) / $indexIdeal * 100, 2)
                    : null;

                $status = null;
                if ($aktual !== null) {
                    if ($aktual > $indexIdeal) {
                        $status = 'Over';
                    } elseif ($aktual < $indexIdeal) {
                        $status = 'Under';
                    } else {
                        $status = 'Sama';
                    }
                }

                $rows[] = [
                    'id' => $fr->id,
                    'source' => 'forecast',
                    'year' => $viewYear,
                    'month' => $month,
                    'month_name' => $this->mlService->monthName($month),
                    'period_label' => $this->mlService->monthName($month).' '.$viewYear,
                    'working_days' => $fr->working_days,
                    'production_volume' => $fr->production_volume,
                    'man_power' => $fr->man_power,
                    'actual_overtime_index' => $aktual,
                    'index_ideal' => round($indexIdeal, 2),
                    'index_benchmark' => round($benchmark, 2),
                    'selisih_ideal_benchmark' => round($selisih, 2),
                    'deviasi_pct' => $deviasi,
                    'status' => $status,
                ];
            } else {
                // No manual input yet → auto-estimate using same-month historical average
                // As more training data accumulates (n grows), these estimates improve automatically.
                $sameMonthRows = $trainingRows->filter(fn (MlTrainingData $r) => $r->month === $month);

                if ($sameMonthRows->isNotEmpty()) {
                    $avgWd = (int) round($sameMonthRows->avg('working_days'));
                    $avgVol = (int) round($sameMonthRows->avg('production_volume'));
                    $avgMp = (int) round($sameMonthRows->avg('man_power'));
                    $nRef = $sameMonthRows->count();

                    $indexIdeal = $this->mlService->predict(
                        (float) $avgWd,
                        (float) $avgVol,
                        (float) $avgMp,
                        $coef,
                    );
                    $benchmark = $benchmarkRatio * $avgVol;
                    $selisih = $indexIdeal - $benchmark;

                    $rows[] = [
                        'id' => null,
                        'source' => 'auto',
                        'n_ref' => $nRef,
                        'year' => $viewYear,
                        'month' => $month,
                        'month_name' => $this->mlService->monthName($month),
                        'period_label' => $this->mlService->monthName($month).' '.$viewYear,
                        'working_days' => $avgWd,
                        'production_volume' => $avgVol,
                        'man_power' => $avgMp,
                        'actual_overtime_index' => null,
                        'index_ideal' => round($indexIdeal, 2),
                        'index_benchmark' => round($benchmark, 2),
                        'selisih_ideal_benchmark' => round($selisih, 2),
                        'deviasi_pct' => null,
                        'status' => null,
                    ];
                } else {
                    // No historical data at all for this month (edge case)
                    $rows[] = [
                        'id' => null,
                        'source' => 'empty',
                        'n_ref' => 0,
                        'year' => $viewYear,
                        'month' => $month,
                        'month_name' => $this->mlService->monthName($month),
                        'period_label' => $this->mlService->monthName($month).' '.$viewYear,
                        'working_days' => null,
                        'production_volume' => null,
                        'man_power' => null,
                        'actual_overtime_index' => null,
                        'index_ideal' => null,
                        'index_benchmark' => null,
                        'selisih_ideal_benchmark' => null,
                        'status' => null,
                    ];
                }
            }
        }

        return Inertia::render('Analytics/Ml/Forecasting', [
            'has_training_data' => true,
            'equation' => $regression['equation'],
            'r_squared' => $regression['r_squared'],
            'benchmark_ratio' => round($benchmarkRatio, 6),
            'forecast_rows' => $rows,
            'n_training' => $trainingRows->count(),
            'view_year' => $viewYear,
        ]);
    }

    /**
     * Store a forecast input row.
     */
    public function storeForecast(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12', Rule::unique('ml_forecast_inputs')->where('year', $request->input('year'))],
            'working_days' => ['required', 'integer', 'min:1', 'max:31'],
            'production_volume' => ['required', 'integer', 'min:1'],
            'man_power' => ['required', 'integer', 'min:1'],
            'actual_overtime_index' => ['nullable', 'integer', 'min:0'],
        ]);

        MlForecastInput::create($data);

        return back()->with('success', 'Data forecast berhasil disimpan.');
    }

    /**
     * Update a forecast input row.
     */
    public function updateForecast(Request $request, MlForecastInput $mlForecastInput): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'working_days' => ['required', 'integer', 'min:1', 'max:31'],
            'production_volume' => ['required', 'integer', 'min:1'],
            'man_power' => ['required', 'integer', 'min:1'],
            'actual_overtime_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $mlForecastInput->update($data);

        return back()->with('success', 'Data forecast berhasil diperbarui.');
    }

    /**
     * Delete a forecast input row.
     */
    public function destroyForecast(Request $request, MlForecastInput $mlForecastInput): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $mlForecastInput->delete();

        return back()->with('success', 'Data forecast berhasil dihapus.');
    }
}
