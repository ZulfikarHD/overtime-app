<?php

namespace App\Services\Analytics;

use App\Models\Department;
use App\Models\Employee;
use App\Models\MlAnomalyLog;
use App\Models\MonthlyBurnSnapshot;
use App\Models\OvertimeItem;
use App\Models\SpklDocument;
use App\Models\User;
use App\Services\Policy\OvertimePolicyEvaluator;
use App\Services\PolicyThresholdService;
use Carbon\Carbon;

class InsightAggregatorService
{
    /**
     * Indonesian month abbreviations.
     *
     * @var array<int, string>
     */
    protected const MONTH_NAMES_ID = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public function __construct(
        public PolicyThresholdService $policyThresholdService,
        public OvertimePolicyEvaluator $policyEvaluator,
    ) {}

    /**
     * Aggregate complete Key Insights and Management Actions dataset for Story E09-11.
     *
     * @return array{
     *     risk_indicators: array{
     *         critical_count: int,
     *         warning_count: int,
     *         info_count: int,
     *         total_risks: int,
     *         all_normal: bool,
     *         items: list<array{
     *             id: string,
     *             type: string,
     *             severity: 'critical'|'warning'|'info',
     *             severity_label: string,
     *             title: string,
     *             description: string,
     *             details_url: string,
     *             details_label: string,
     *             meta: array<string, mixed>
     *         }>
     *     },
     *     anomaly_detection: array{
     *         labels: list<string>,
     *         dates: list<string>,
     *         daily_hours: list<float>,
     *         mean: float,
     *         std_dev: float,
     *         upper_band: float,
     *         lower_band: float,
     *         unusual_patterns_count: int,
     *         summary: string,
     *         anomalies: list<array{
     *             date: string,
     *             label: string,
     *             index: int,
     *             hours: float,
     *             anomaly_score: float,
     *             reasons: list<string>
     *         }>
     *     },
     *     action_items: list<array{
     *         id: string,
     *         priority: 'high'|'medium'|'low',
     *         priority_label: string,
     *         action_item: string,
     *         department: string,
     *         department_id: int|null,
     *         impact: string,
     *         deadline: string,
     *         status: 'pending'|'in_progress'|'resolved',
     *         status_label: string,
     *         resolution_note: string|null,
     *         updated_at: string|null,
     *         updated_by_user_id: int|null,
     *         source_risk_type: string
     *     }>,
     *     scope: array{
     *         department_id: int|null,
     *         department_name: string,
     *         start_date: string,
     *         end_date: string,
     *         fiscal_year: int,
     *         fiscal_month: int
     *     }
     * }
     */
    public function getInsightsData(
        User $user,
        ?int $departmentId = null,
        ?string $startDate = null,
        ?string $endDate = null,
    ): array {
        // Enforce department scoping for managers
        if ($user->isManager()) {
            $departmentId = (int) $user->department_id;
        }

        $now = Carbon::now('Asia/Jakarta');
        $referenceEnd = $endDate ? Carbon::parse($endDate, 'Asia/Jakarta') : $now->copy();
        $referenceStart = $startDate ? Carbon::parse($startDate, 'Asia/Jakarta') : $referenceEnd->copy()->startOfMonth();

        $fiscalYear = (int) $referenceEnd->year;
        $fiscalMonth = (int) $referenceEnd->month;

        $department = $departmentId ? Department::find($departmentId) : null;
        $departmentName = $department?->name ?? __('Semua Departemen (Lintas Pabrik)');

        // 1. Compute 30-Day Anomaly Detection
        $anomalyData = $this->compute30DayAnomalyDetection($departmentId, $referenceEnd);

        // 2. Compute Risk Indicators & Action Items
        $userPrefs = is_array($user->preferences) ? $user->preferences : [];
        $savedActionStates = is_array($userPrefs['action_items'] ?? null) ? $userPrefs['action_items'] : [];

        $riskEvaluation = $this->evaluateRisksAndActions(
            $departmentId,
            $departmentName,
            $fiscalYear,
            $fiscalMonth,
            $referenceEnd,
            $savedActionStates,
            $anomalyData
        );

        return [
            'risk_indicators' => $riskEvaluation['risk_indicators'],
            'anomaly_detection' => $anomalyData,
            'action_items' => $riskEvaluation['action_items'],
            'scope' => [
                'department_id' => $departmentId,
                'department_name' => $departmentName,
                'start_date' => $referenceStart->toDateString(),
                'end_date' => $referenceEnd->toDateString(),
                'fiscal_year' => $fiscalYear,
                'fiscal_month' => $fiscalMonth,
            ],
        ];
    }

    /**
     * Compute 30-day statistical anomaly detection and scatter markers.
     *
     * @return array{
     *     labels: list<string>,
     *     dates: list<string>,
     *     daily_hours: list<float>,
     *     mean: float,
     *     std_dev: float,
     *     upper_band: float,
     *     lower_band: float,
     *     unusual_patterns_count: int,
     *     summary: string,
     *     anomalies: list<array{
     *         date: string,
     *         label: string,
     *         index: int,
     *         hours: float,
     *         anomaly_score: float,
     *         reasons: list<string>
     *     }>
     * }
     */
    protected function compute30DayAnomalyDetection(?int $departmentId, Carbon $referenceEnd): array
    {
        $startDate30Days = $referenceEnd->copy()->subDays(29)->startOfDay();
        $endDate30Days = $referenceEnd->copy()->endOfDay();

        // 1. Query daily total overtime hours from approved submissions
        /** @var array<string, float> $dailyHoursMap */
        $dailyHoursMap = [];
        $rawItemRows = OvertimeItem::query()
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->where('overtime_items.status', 'APPROVED')
            ->whereNotIn('overtime_submissions.status', ['REJECTED', 'DRAFT'])
            ->whereDate('overtime_submissions.operational_date', '>=', $startDate30Days->toDateString())
            ->whereDate('overtime_submissions.operational_date', '<=', $endDate30Days->toDateString())
            ->when($departmentId !== null, function ($query) use ($departmentId) {
                $query->where('overtime_submissions.department_id', $departmentId);
            })
            ->selectRaw('overtime_submissions.operational_date, SUM(overtime_items.total_hours) as total_hours')
            ->groupBy('overtime_submissions.operational_date')
            ->get();

        foreach ($rawItemRows as $row) {
            $dateKey = Carbon::parse($row->operational_date)->toDateString();
            $dailyHoursMap[$dateKey] = ($dailyHoursMap[$dateKey] ?? 0.0) + (float) $row->total_hours;
        }

        // 2. Query ML anomaly logs in the 30-day window
        /** @var array<string, array{score: float, reasons: list<string>}> $mlAnomaliesMap */
        $mlAnomaliesMap = [];
        $mlRecords = MlAnomalyLog::query()
            ->where('is_dismissed', false)
            ->join('overtime_items', 'ml_anomaly_logs.overtime_item_id', '=', 'overtime_items.id')
            ->join('overtime_submissions', 'overtime_items.overtime_submission_id', '=', 'overtime_submissions.id')
            ->whereDate('overtime_submissions.operational_date', '>=', $startDate30Days->toDateString())
            ->whereDate('overtime_submissions.operational_date', '<=', $endDate30Days->toDateString())
            ->when($departmentId !== null, function ($query) use ($departmentId) {
                $query->where('overtime_submissions.department_id', $departmentId);
            })
            ->select([
                'overtime_submissions.operational_date',
                'ml_anomaly_logs.anomaly_score',
                'ml_anomaly_logs.anomaly_reasons',
            ])
            ->get();

        foreach ($mlRecords as $rec) {
            $dateKey = Carbon::parse($rec->operational_date)->toDateString();
            /** @var list<string> $reasons */
            $reasons = is_array($rec->anomaly_reasons) ? $rec->anomaly_reasons : [];
            $mlAnomaliesMap[$dateKey] = [
                'score' => (float) $rec->anomaly_score,
                'reasons' => $reasons,
            ];
        }

        $labels = [];
        $dates = [];
        $hoursList = [];
        $curr = $startDate30Days->copy();

        for ($i = 0; $i < 30; $i++) {
            $dateStr = $curr->toDateString();
            $dates[] = $dateStr;
            $monthNum = (int) $curr->format('n');
            $monthLabel = self::MONTH_NAMES_ID[$monthNum] ?? $curr->format('M');
            $labels[] = $curr->format('j').' '.$monthLabel;

            $hours = (float) ($dailyHoursMap[$dateStr] ?? 0.0);
            $hoursList[] = round($hours, 1);
            $curr->addDay();
        }

        $sum = array_sum($hoursList);
        $mean = round($sum / 30, 2);

        $varianceSum = 0.0;
        foreach ($hoursList as $h) {
            $varianceSum += pow($h - $mean, 2);
        }
        $stdDev = round(sqrt($varianceSum / 30), 2);
        $upperBand = round($mean + $stdDev, 2);
        $lowerBand = round(max(0.0, $mean - $stdDev), 2);

        $anomalies = [];
        foreach ($hoursList as $idx => $h) {
            $dateStr = $dates[$idx];
            $hasMlAnomaly = isset($mlAnomaliesMap[$dateStr]);
            $isStatAnomaly = ($h > $upperBand && $h > 0.0);

            if ($isStatAnomaly || $hasMlAnomaly) {
                $score = $hasMlAnomaly
                    ? $mlAnomaliesMap[$dateStr]['score']
                    : round(($h - $mean) / max(1.0, $stdDev), 2);

                $reasons = [];
                if ($isStatAnomaly) {
                    $reasons[] = __('Lonjakan jam lembur (:hours jam) melebihi batas atas rata-rata (:upper jam)', [
                        'hours' => $h,
                        'upper' => $upperBand,
                    ]);
                }
                if ($hasMlAnomaly) {
                    $mlReasons = $mlAnomaliesMap[$dateStr]['reasons'];
                    if (! empty($mlReasons)) {
                        $reasons = array_merge($reasons, $mlReasons);
                    } else {
                        $reasons[] = __('Terdeteksi deviasi pola oleh model machine learning');
                    }
                }

                $anomalies[] = [
                    'date' => $dateStr,
                    'label' => $labels[$idx],
                    'index' => $idx,
                    'hours' => $h,
                    'anomaly_score' => $score,
                    'reasons' => $reasons,
                ];
            }
        }

        $unusualCount = count($anomalies);
        $summary = __('Terdeteksi :count pola tidak biasa dalam 30 hari terakhir', ['count' => $unusualCount]);

        return [
            'labels' => $labels,
            'dates' => $dates,
            'daily_hours' => $hoursList,
            'mean' => $mean,
            'std_dev' => $stdDev,
            'upper_band' => $upperBand,
            'lower_band' => $lowerBand,
            'unusual_patterns_count' => $unusualCount,
            'summary' => $summary,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Evaluate system-generated risks from live data and generate prioritized action items.
     *
     * @param  array<string, mixed>  $savedActionStates
     * @param  array<string, mixed>  $anomalyData
     * @return array{
     *     risk_indicators: array{
     *         critical_count: int,
     *         warning_count: int,
     *         info_count: int,
     *         total_risks: int,
     *         all_normal: bool,
     *         items: list<array{
     *             id: string,
     *             type: string,
     *             severity: 'critical'|'warning'|'info',
     *             severity_label: string,
     *             title: string,
     *             description: string,
     *             details_url: string,
     *             details_label: string,
     *             meta: array<string, mixed>
     *         }>
     *     },
     *     action_items: list<array{
     *         id: string,
     *         priority: 'high'|'medium'|'low',
     *         priority_label: string,
     *         action_item: string,
     *         department: string,
     *         department_id: int|null,
     *         impact: string,
     *         deadline: string,
     *         status: 'pending'|'in_progress'|'resolved',
     *         status_label: string,
     *         resolution_note: string|null,
     *         updated_at: string|null,
     *         updated_by_user_id: int|null,
     *         source_risk_type: string
     *     }>
     * }
     */
    protected function evaluateRisksAndActions(
        ?int $departmentId,
        string $departmentName,
        int $fiscalYear,
        int $fiscalMonth,
        Carbon $referenceEnd,
        array $savedActionStates,
        array $anomalyData,
    ): array {
        $riskItems = [];
        $actionItems = [];

        // ==========================================
        // 1. Budget Overrun Risk Evaluation
        // ==========================================
        $snapshotsQuery = MonthlyBurnSnapshot::query()
            ->with(['department', 'section'])
            ->where('fiscal_year', $fiscalYear)
            ->where('fiscal_month', $fiscalMonth);

        if ($departmentId !== null) {
            $snapshotsQuery->where('department_id', $departmentId);
        }

        $snapshots = $snapshotsQuery->get();

        foreach ($snapshots as $snapshot) {
            $threshold = $this->policyThresholdService->getForDepartment($snapshot->department_id);
            $warningPct = (float) $threshold->burn_warning_pct;
            $dangerPct = (float) $threshold->burn_danger_pct;
            $burnPct = (float) $snapshot->burn_index_pct;
            $secName = $snapshot->section?->name ?? 'Section #'.$snapshot->section_id;
            $deptName = $snapshot->department?->name ?? $departmentName;

            if ($burnPct > $dangerPct || $burnPct > 100.0) {
                $riskId = "risk-budget-{$snapshot->section_id}-{$fiscalYear}-{$fiscalMonth}";
                $riskItems[] = [
                    'id' => $riskId,
                    'type' => 'budget_overrun',
                    'severity' => 'critical',
                    'severity_label' => __('Kritis'),
                    'title' => __('Defisit Anggaran Lembur: :section', ['section' => $secName]),
                    'description' => __('Burn Index mencapai :pct% (:actual/:planned jam). Melebihi plafon risiko bahaya (:danger%).', [
                        'pct' => number_format($burnPct, 1),
                        'actual' => number_format((float) $snapshot->cumulative_actual_hours, 1),
                        'planned' => number_format((float) $snapshot->planned_budget_hours, 1),
                        'danger' => number_format($dangerPct, 1),
                    ]),
                    'details_url' => '/dashboard?tab=pacing',
                    'details_label' => __('Lihat Rincian Laju Seksi →'),
                    'meta' => [
                        'section_id' => $snapshot->section_id,
                        'department_id' => $snapshot->department_id,
                        'burn_index_pct' => $burnPct,
                    ],
                ];

                $actId = "ACT-BUDGET-{$snapshot->section_id}-{$fiscalYear}-{$fiscalMonth}";
                $actionItems[] = $this->formatActionItem(
                    $actId,
                    'high',
                    __('Audit & Penyesuaian Kuota Lembur Seksi :section', ['section' => $secName]),
                    $deptName,
                    $snapshot->department_id,
                    __('Mencegah pembengkakan anggaran lebih lanjut (Burn Index :pct%)', ['pct' => number_format($burnPct, 1)]),
                    $referenceEnd->copy()->endOfMonth()->toDateString(),
                    $savedActionStates[$actId] ?? null,
                    'budget_overrun'
                );
            } elseif ($burnPct > $warningPct) {
                $riskId = "risk-budget-{$snapshot->section_id}-{$fiscalYear}-{$fiscalMonth}";
                $riskItems[] = [
                    'id' => $riskId,
                    'type' => 'budget_overrun',
                    'severity' => 'warning',
                    'severity_label' => __('Peringatan'),
                    'title' => __('Peringatan Plafon Anggaran: :section', ['section' => $secName]),
                    'description' => __('Burn Index mencapai :pct%. Berada dalam zona peringatan (:warn% - :danger%).', [
                        'pct' => number_format($burnPct, 1),
                        'warn' => number_format($warningPct, 1),
                        'danger' => number_format($dangerPct, 1),
                    ]),
                    'details_url' => '/dashboard?tab=pacing',
                    'details_label' => __('Lihat Rincian Laju Seksi →'),
                    'meta' => [
                        'section_id' => $snapshot->section_id,
                        'department_id' => $snapshot->department_id,
                        'burn_index_pct' => $burnPct,
                    ],
                ];

                $actId = "ACT-BUDGET-{$snapshot->section_id}-{$fiscalYear}-{$fiscalMonth}";
                $actionItems[] = $this->formatActionItem(
                    $actId,
                    'medium',
                    __('Evaluasi Alokasi Jam Lembur Seksi :section', ['section' => $secName]),
                    $deptName,
                    $snapshot->department_id,
                    __('Mengendalikan konsumsi kuota lembur mendekati batas plafon (:pct%)', ['pct' => number_format($burnPct, 1)]),
                    $referenceEnd->copy()->endOfMonth()->toDateString(),
                    $savedActionStates[$actId] ?? null,
                    'budget_overrun'
                );
            }
        }

        // ==========================================
        // 2. Employee Burnout Risk Evaluation (Epic-06)
        // ==========================================
        $recentCandidates = Employee::query()
            ->where('is_active', true)
            ->when($departmentId !== null, function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            })
            ->whereHas('overtimeItems', function ($q) use ($referenceEnd) {
                $q->where('status', 'APPROVED')
                    ->whereHas('overtimeSubmission', function ($sq) use ($referenceEnd) {
                        $sq->whereBetween('operational_date', [
                            $referenceEnd->copy()->subWeeks(4)->toDateString(),
                            $referenceEnd->toDateString(),
                        ]);
                    });
            })
            ->with(['department', 'section'])
            ->take(40)
            ->get();

        $burnoutCount = 0;
        foreach ($recentCandidates as $employee) {
            $threshold = $this->policyThresholdService->getForDepartment($employee->department_id);
            $warning = $this->policyEvaluator->evaluateEmployee($employee->id, 0.0, $referenceEnd->toDateString());

            if ($warning->consecutiveWeeks >= (int) $threshold->consecutive_weeks_alert) {
                $burnoutCount++;
                $isCritical = $warning->consecutiveWeeks >= 4 || $warning->weeklyTotal > ((float) $threshold->weekly_soft_limit_hours * 1.5);
                $severity = $isCritical ? 'critical' : 'warning';
                $severityLabel = $isCritical ? __('Kritis') : __('Peringatan');
                $deptName = $employee->department?->name ?? $departmentName;

                $riskId = "risk-burnout-{$employee->id}-{$fiscalYear}-{$fiscalMonth}";
                $riskItems[] = [
                    'id' => $riskId,
                    'type' => 'employee_burnout',
                    'severity' => $severity,
                    'severity_label' => $severityLabel,
                    'title' => __('Risiko Kelelahan Karyawan: :name', ['name' => $employee->full_name]),
                    'description' => __(':weeks pekan berturut-turut melebihi batas :limit jam (pekan ini: :hours jam). Rekomendasi rotasi shift.', [
                        'weeks' => $warning->consecutiveWeeks,
                        'limit' => number_format((float) $threshold->weekly_soft_limit_hours, 1),
                        'hours' => number_format((float) $warning->weeklyTotal, 1),
                    ]),
                    'details_url' => '/dashboard?tab=employees',
                    'details_label' => __('Lihat Daftar Karyawan →'),
                    'meta' => [
                        'employee_id' => $employee->id,
                        'npk' => $employee->npk,
                        'consecutive_weeks' => $warning->consecutiveWeeks,
                    ],
                ];

                $actId = "ACT-BURNOUT-{$employee->id}-{$fiscalYear}-{$fiscalMonth}";
                $actionItems[] = $this->formatActionItem(
                    $actId,
                    'high',
                    __('Rotasi Shift & Evaluasi Beban Kerja: :name (:npk)', ['name' => $employee->full_name, 'npk' => $employee->npk]),
                    $deptName,
                    $employee->department_id,
                    __('Mitigasi kelelahan kronis & risiko safety operasional (:weeks pekan berturut-turut)', ['weeks' => $warning->consecutiveWeeks]),
                    $referenceEnd->copy()->addDays(3)->toDateString(),
                    $savedActionStates[$actId] ?? null,
                    'employee_burnout'
                );
            }
        }

        // ==========================================
        // 3. Efficiency Drop & Anomaly Spike Evaluation
        // ==========================================
        if ($anomalyData['unusual_patterns_count'] > 0) {
            $isCritical = $anomalyData['unusual_patterns_count'] >= 3;
            $severity = $isCritical ? 'critical' : 'warning';
            $severityLabel = $isCritical ? __('Kritis') : __('Peringatan');

            $riskId = "risk-anomaly-spike-{$fiscalYear}-{$fiscalMonth}";
            $riskItems[] = [
                'id' => $riskId,
                'type' => 'efficiency_drop',
                'severity' => $severity,
                'severity_label' => $severityLabel,
                'title' => __('Lonjakan Lembur & Deviasi Efisiensi (:count Pola)', ['count' => $anomalyData['unusual_patterns_count']]),
                'description' => __('Terdeteksi :count hari lonjakan jam lembur melebihi batas atas deviasi standar (rata-rata :mean jam/hari, batas atas :upper jam).', [
                    'count' => $anomalyData['unusual_patterns_count'],
                    'mean' => number_format((float) $anomalyData['mean'], 1),
                    'upper' => number_format((float) $anomalyData['upper_band'], 1),
                ]),
                'details_url' => '#anomaly-chart',
                'details_label' => __('Lihat Grafik Deteksi Anomali ↓'),
                'meta' => [
                    'unusual_patterns_count' => $anomalyData['unusual_patterns_count'],
                    'mean' => $anomalyData['mean'],
                    'upper_band' => $anomalyData['upper_band'],
                ],
            ];

            // Add action items for top anomaly dates
            $anomalyDates = array_slice($anomalyData['anomalies'], 0, 3);
            foreach ($anomalyDates as $anom) {
                $actId = "ACT-ANOMALY-{$anom['date']}";
                $actionItems[] = $this->formatActionItem(
                    $actId,
                    'medium',
                    __('Investigasi Penyebab (RCA) Lonjakan Lembur Tanggal :date', ['date' => $anom['label']]),
                    $departmentName,
                    $departmentId,
                    __('Identifikasi downtime mesin atau hambatan produksi (:hours jam)', ['hours' => number_format((float) $anom['hours'], 1)]),
                    $referenceEnd->copy()->addDays(5)->toDateString(),
                    $savedActionStates[$actId] ?? null,
                    'efficiency_drop'
                );
            }
        }

        // ==========================================
        // 4. Overdue SPKL Compliance Evaluation
        // ==========================================
        $overdueSpkl = SpklDocument::query()
            ->where('spkl_documents.status', 'PENDING')
            ->where('spkl_documents.due_date', '<', $referenceEnd->toDateString())
            ->join('overtime_submissions', 'spkl_documents.overtime_submission_id', '=', 'overtime_submissions.id')
            ->when($departmentId !== null, function ($query) use ($departmentId) {
                $query->where('overtime_submissions.department_id', $departmentId);
            })
            ->select([
                'spkl_documents.id',
                'spkl_documents.spkl_number',
                'spkl_documents.due_date',
                'overtime_submissions.submission_code',
                'overtime_submissions.department_id',
            ])
            ->take(10)
            ->get();

        if ($overdueSpkl->isNotEmpty()) {
            $count = $overdueSpkl->count();
            $riskId = "risk-spkl-overdue-{$fiscalYear}-{$fiscalMonth}";
            $riskItems[] = [
                'id' => $riskId,
                'type' => 'spkl_overdue',
                'severity' => 'warning',
                'severity_label' => __('Peringatan'),
                'title' => __('Kepatuhan Dokumen SPKL (:count Berkas Melewati Batas)', ['count' => $count]),
                'description' => __('Terdapat :count berkas SPKL belum melampirkan fisik dokumen bertanda tangan setelah grace period 48 jam.', ['count' => $count]),
                'details_url' => '/overtime/submissions',
                'details_label' => __('Lihat Antrean Pengajuan Lembur →'),
                'meta' => [
                    'overdue_count' => $count,
                ],
            ];

            $firstSpkl = $overdueSpkl->first();
            $actId = "ACT-SPKL-{$firstSpkl->id}";
            $actionItems[] = $this->formatActionItem(
                $actId,
                'medium',
                __('Verifikasi & Unggah Fisik SPKL (:count Berkas Tertunda)', ['count' => $count]),
                $departmentName,
                $departmentId,
                __('Kepatuhan audit ketenagakerjaan dan penutupan grace period SPKL'),
                $referenceEnd->copy()->addDays(1)->toDateString(),
                $savedActionStates[$actId] ?? null,
                'spkl_overdue'
            );
        }

        // Aggregate severity counts
        $criticalCount = 0;
        $warningCount = 0;
        $infoCount = 0;

        foreach ($riskItems as $item) {
            if ($item['severity'] === 'critical') {
                $criticalCount++;
            } elseif ($item['severity'] === 'warning') {
                $warningCount++;
            } else {
                $infoCount++;
            }
        }

        $totalRisks = count($riskItems);
        $allNormal = ($totalRisks === 0);

        return [
            'risk_indicators' => [
                'critical_count' => $criticalCount,
                'warning_count' => $warningCount,
                'info_count' => $infoCount,
                'total_risks' => $totalRisks,
                'all_normal' => $allNormal,
                'items' => $riskItems,
            ],
            'action_items' => $actionItems,
        ];
    }

    /**
     * Format a single management action item record with saved state integration.
     *
     * @param  array<string, mixed>|null  $savedState
     * @return array{
     *     id: string,
     *     priority: 'high'|'medium'|'low',
     *     priority_label: string,
     *     action_item: string,
     *     department: string,
     *     department_id: int|null,
     *     impact: string,
     *     deadline: string,
     *     status: 'pending'|'in_progress'|'resolved',
     *     status_label: string,
     *     resolution_note: string|null,
     *     updated_at: string|null,
     *     updated_by_user_id: int|null,
     *     source_risk_type: string
     * }
     */
    protected function formatActionItem(
        string $id,
        string $priority,
        string $actionItemText,
        string $department,
        ?int $departmentId,
        string $impact,
        string $deadline,
        ?array $savedState,
        string $sourceRiskType,
    ): array {
        $status = 'pending';
        $resolutionNote = null;
        $updatedAt = null;
        $updatedByUserId = null;

        if (is_array($savedState)) {
            $rawStatus = (string) ($savedState['status'] ?? 'pending');
            if (in_array($rawStatus, ['pending', 'in_progress', 'resolved'], true)) {
                $status = $rawStatus;
            }
            $resolutionNote = isset($savedState['resolution_note']) ? (string) $savedState['resolution_note'] : null;
            $updatedAt = isset($savedState['updated_at']) ? (string) $savedState['updated_at'] : null;
            $updatedByUserId = isset($savedState['updated_by_user_id']) ? (int) $savedState['updated_by_user_id'] : null;
        }

        $priorityLabels = [
            'high' => __('Tinggi'),
            'medium' => __('Sedang'),
            'low' => __('Rendah'),
        ];

        $statusLabels = [
            'pending' => __('Tertunda'),
            'in_progress' => __('Dalam Pengerjaan'),
            'resolved' => __('Selesai'),
        ];

        return [
            'id' => $id,
            'priority' => in_array($priority, ['high', 'medium', 'low'], true) ? $priority : 'medium',
            'priority_label' => $priorityLabels[$priority] ?? __('Sedang'),
            'action_item' => $actionItemText,
            'department' => $department,
            'department_id' => $departmentId,
            'impact' => $impact,
            'deadline' => $deadline,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? __('Tertunda'),
            'resolution_note' => $resolutionNote,
            'updated_at' => $updatedAt,
            'updated_by_user_id' => $updatedByUserId,
            'source_risk_type' => $sourceRiskType,
        ];
    }

    /**
     * Update an action item status and optional resolution remarks in user preferences.
     *
     * @return array{
     *     id: string,
     *     status: 'pending'|'in_progress'|'resolved',
     *     status_label: string,
     *     resolution_note: string|null,
     *     updated_at: string,
     *     updated_by_user_id: int
     * }
     */
    public function updateActionItemStatus(
        User $user,
        string $actionItemId,
        string $status,
        ?string $resolutionNote = null,
    ): array {
        if (! in_array($status, ['pending', 'in_progress', 'resolved'], true)) {
            $status = 'pending';
        }

        $prefs = is_array($user->preferences) ? $user->preferences : [];
        if (! isset($prefs['action_items']) || ! is_array($prefs['action_items'])) {
            $prefs['action_items'] = [];
        }

        $nowIso = Carbon::now('Asia/Jakarta')->toIso8601String();

        $prefs['action_items'][$actionItemId] = [
            'status' => $status,
            'resolution_note' => $resolutionNote,
            'updated_at' => $nowIso,
            'updated_by_user_id' => $user->id,
        ];

        $user->update(['preferences' => $prefs]);

        $statusLabels = [
            'pending' => __('Tertunda'),
            'in_progress' => __('Dalam Pengerjaan'),
            'resolved' => __('Selesai'),
        ];

        return [
            'id' => $actionItemId,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? __('Tertunda'),
            'resolution_note' => $resolutionNote,
            'updated_at' => $nowIso,
            'updated_by_user_id' => $user->id,
        ];
    }
}
