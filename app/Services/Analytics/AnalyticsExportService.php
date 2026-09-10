<?php

namespace App\Services\Analytics;

use App\Models\Department;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportService
{
    /**
     * Map internal tab slugs to user-facing labels in Indonesian.
     *
     * @var array<string, string>
     */
    public const TAB_LABELS = [
        'predictive' => 'Prediksi Lembur (Predictive Analytics)',
        'cost' => 'Analisis Biaya (Cost Analysis)',
        'correlation' => 'Korelasi & Pola (Correlation & Patterns)',
        'scenario' => 'Simulasi Skenario (What-If Scenarios)',
        'insights' => 'Wawasan Kunci (Key Insights & Risk Triage)',
        'comparison' => 'Perbandingan Periode (Period Benchmarking)',
    ];

    public function __construct(
        public ?PredictiveAnalyticsService $predictiveService = null,
        public ?CostAnalysisService $costService = null,
        public ?CorrelationAnalysisService $correlationService = null,
        public ?InsightAggregatorService $insightService = null,
        public ?PeriodComparisonService $comparisonService = null,
    ) {
        $this->predictiveService = $predictiveService ?? app(PredictiveAnalyticsService::class);
        $this->costService = $costService ?? app(CostAnalysisService::class);
        $this->correlationService = $correlationService ?? app(CorrelationAnalysisService::class);
        $this->insightService = $insightService ?? app(InsightAggregatorService::class);
        $this->comparisonService = $comparisonService ?? app(PeriodComparisonService::class);
    }

    /**
     * Export analytics data as PDF or CSV.
     *
     * @param  array<string, mixed>  $params
     */
    public function export(User $user, array $params, string $format = 'pdf'): SymfonyResponse
    {
        $tab = (string) ($params['tab'] ?? 'predictive');
        if (! array_key_exists($tab, self::TAB_LABELS)) {
            $tab = 'predictive';
        }

        $departmentId = isset($params['department_id']) && $params['department_id'] !== '' && $params['department_id'] !== 'all'
            ? (int) $params['department_id']
            : null;

        // Managers are strictly scoped to their department
        if ($user->isManager()) {
            $departmentId = (int) $user->department_id;
        }

        $startDate = ! empty($params['start_date'])
            ? (string) $params['start_date']
            : Carbon::now('Asia/Jakarta')->startOfMonth()->toDateString();

        $endDate = ! empty($params['end_date'])
            ? (string) $params['end_date']
            : Carbon::now('Asia/Jakarta')->endOfMonth()->toDateString();

        $department = $departmentId ? Department::find($departmentId) : null;
        $departmentName = $department?->name ?? 'Semua Departemen (Konsolidasi Pabrik)';

        $timestamp = Carbon::now('Asia/Jakarta')->format('Ymd_His');
        $tabSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', $tab);
        $baseFilename = "Analytics_{$tabSlug}_{$timestamp}";

        if (strtolower($format) === 'csv') {
            return $this->exportCsv($user, $tab, $departmentName, $departmentId, $startDate, $endDate, $baseFilename.'.csv', $params);
        }

        return $this->exportPdf($user, $tab, $departmentName, $departmentId, $startDate, $endDate, $baseFilename.'.pdf', $params);
    }

    /**
     * Export analytics summary as a streamed CSV with UTF-8 BOM.
     *
     * @param  array<string, mixed>  $params
     */
    protected function exportCsv(
        User $user,
        string $tab,
        string $departmentName,
        ?int $departmentId,
        string $startDate,
        string $endDate,
        string $filename,
        array $params = [],
    ): StreamedResponse {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($user, $tab, $departmentName, $departmentId, $startDate, $endDate, $params) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['PT ISUZU ASTRA MOTOR INDONESIA']);
            fputcsv($handle, ['MODUL ANALITIK & KEPUTUSAN STRATEGIS (EXECUTIVE REPORT)']);
            fputcsv($handle, []);
            fputcsv($handle, ['Parameter', 'Nilai']);
            fputcsv($handle, ['Modul Analitik', self::TAB_LABELS[$tab] ?? $tab]);
            fputcsv($handle, ['Cakupan Departemen', $departmentName]);
            fputcsv($handle, ['Tanggal Mulai', $startDate]);
            fputcsv($handle, ['Tanggal Selesai', $endDate]);
            fputcsv($handle, ['Diekspor Oleh', "{$user->name} ({$user->npk})"]);
            fputcsv($handle, ['Waktu Ekspor', Carbon::now('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB']);
            fputcsv($handle, []);

            if ($tab === 'predictive') {
                $pred = $this->predictiveService->getPredictiveData($user, $departmentId, $startDate, $endDate);
                fputcsv($handle, ['=== INDIKATOR PREDIKSI LEMBUR (E09-07) ===']);
                fputcsv($handle, ['Metrik', 'Nilai', 'Deskripsi']);
                fputcsv($handle, ['Prediksi Bulan Depan', $pred['kpi']['formatted_prediction'], "Model: {$pred['kpi']['model_name']}"]);
                fputcsv($handle, ['Tingkat Akurasi (MAPE)', $pred['kpi']['accuracy_label'], $pred['kpi']['accuracy_description']]);
                fputcsv($handle, ['Pola Musiman', $pred['kpi']['seasonal_pattern'], $pred['kpi']['seasonal_description']]);
                fputcsv($handle, ['Arah Tren', $pred['kpi']['trend_label'], $pred['kpi']['trend_description']]);
                fputcsv($handle, []);
                fputcsv($handle, ['=== RINCIAN PREDIKSI PER SEKSI ===']);
                fputcsv($handle, ['No', 'Kode Seksi', 'Nama Seksi', 'Prediksi (Jam)', 'Batas Bawah', 'Batas Atas', 'Margin (±Jam)', 'Metode']);
                foreach ($pred['section_forecast']['sections'] as $idx => $sec) {
                    fputcsv($handle, [
                        $idx + 1,
                        $sec['section_code'],
                        $sec['section_name'],
                        $sec['predicted_hours'],
                        $sec['ci_lower'],
                        $sec['ci_upper'],
                        $sec['margin'],
                        $sec['fallback_used'] ? 'Moving Average' : 'Supervised ML',
                    ]);
                }
            } elseif ($tab === 'cost') {
                $cost = $this->costService->getCostData($user, $departmentId, $startDate, $endDate);
                fputcsv($handle, ['=== INDIKATOR BIAYA LEMBUR (E09-08) ===']);
                fputcsv($handle, ['Metrik', 'Nilai', 'Deskripsi']);
                fputcsv($handle, ['Total Biaya Lembur', $cost['kpi']['formatted_total_cost'], 'Rp '.number_format($cost['kpi']['total_cost'], 0, ',', '.')]);
                fputcsv($handle, ['Sisa Anggaran', $cost['kpi']['formatted_remaining_budget'], "Konsumsi: {$cost['kpi']['budget_consumption_pct']}%"]);
                fputcsv($handle, ['Rata-rata Biaya / Karyawan', $cost['kpi']['formatted_avg_cost_per_employee'], "Headcount: {$cost['kpi']['active_employee_count']} orang"]);
                fputcsv($handle, ['Rasio Biaya CapEx', "{$cost['kpi']['capex_ratio_pct']}%", 'Belanja modal terkapitalisasi']);
                fputcsv($handle, []);
                fputcsv($handle, ['=== RINCIAN BIAYA PER DEPARTEMEN ===']);
                fputcsv($handle, ['No', 'Kode Departemen', 'Nama Departemen', 'Total Jam', 'Tarif Rata-rata (Rp/Jam)', 'Total Biaya (Rp)', 'Plafon Anggaran (Rp)', 'Konsumsi (%)', 'Biaya CapEx (Rp)', 'Biaya OpEx (Rp)', 'Tren MoM']);
                foreach ($cost['department_costs'] as $idx => $dept) {
                    fputcsv($handle, [
                        $idx + 1,
                        $dept['department_code'],
                        $dept['department_name'],
                        $dept['total_hours'],
                        $dept['formatted_avg_rate'],
                        number_format($dept['total_cost'], 0, ',', '.'),
                        number_format($dept['planned_cost'], 0, ',', '.'),
                        "{$dept['budget_consumption_pct']}%",
                        number_format($dept['capex_cost'], 0, ',', '.'),
                        number_format($dept['opex_cost'], 0, ',', '.'),
                        strtoupper($dept['trend'])." ({$dept['trend_variance_pct']}%)",
                    ]);
                }
            } elseif ($tab === 'correlation') {
                $corr = $this->correlationService->getCorrelationData($user, $departmentId, $startDate, $endDate);
                fputcsv($handle, ['=== INDIKATOR ZONA LEMBUR OPTIMAL (E09-09) ===']);
                fputcsv($handle, ['Indikator', 'Nilai', 'Deskripsi']);
                fputcsv($handle, ['Zona Lembur Wajar (Sweet Spot)', "{$corr['kpi']['sweet_spot_min']} - {$corr['kpi']['sweet_spot_max']} jam/minggu", 'Batas efisiensi optimal']);
                fputcsv($handle, ['Titik Puncak Produktivitas', "{$corr['kpi']['peak_efficiency_hours']} jam/minggu", 'Rata-rata output tertinggi']);
                fputcsv($handle, ['Ambang Batas Kelelahan', "> {$corr['kpi']['warning_threshold_hours']} jam/minggu", 'Batas kebijakan']);
                fputcsv($handle, ['Rata-rata Jam Mingguan Saat Ini', "{$corr['kpi']['current_weekly_avg_hours']} jam/minggu", $corr['kpi']['current_zone_label']]);
                fputcsv($handle, []);
                fputcsv($handle, ['=== MATRIKS KORELASI BIVARIAT ===']);
                $headers = array_merge(['Variabel'], array_column($corr['correlation_matrix']['variables'], 'label'));
                fputcsv($handle, $headers);
                foreach ($corr['correlation_matrix']['matrix'] as $rowIdx => $row) {
                    $rowVals = [$corr['correlation_matrix']['variables'][$rowIdx]['label']];
                    foreach ($row as $cell) {
                        $rowVals[] = $cell['r'] !== null ? (string) $cell['r'] : 'Menunggu ERP';
                    }
                    fputcsv($handle, $rowVals);
                }
                fputcsv($handle, []);
                fputcsv($handle, ['=== DATA SCATTER LEMBUR VS PRODUKSI ===']);
                if ($corr['overtime_vs_production']['erp_connected']) {
                    fputcsv($handle, ['Bulan', 'Kode Seksi', 'Nama Seksi', 'Volume Produksi (Unit)', 'Jam Lembur (Jam)']);
                    foreach ($corr['overtime_vs_production']['scatter_points'] as $sp) {
                        fputcsv($handle, [
                            $sp['month'],
                            $sp['section_code'],
                            $sp['section_name'],
                            $sp['x'],
                            $sp['y'],
                        ]);
                    }
                } else {
                    fputcsv($handle, [$corr['overtime_vs_production']['message']]);
                }
            } elseif ($tab === 'insights') {
                $insights = $this->insightService->getInsightsData($user, $departmentId, $startDate, $endDate);
                fputcsv($handle, ['=== INDIKATOR RISIKO & PERINGATAN OTOMATIS (E09-11) ===']);
                fputcsv($handle, ['Total Risiko Teridentifikasi', $insights['risk_indicators']['total_risks']]);
                fputcsv($handle, ['Status Kritis', $insights['risk_indicators']['critical_count']]);
                fputcsv($handle, ['Status Peringatan', $insights['risk_indicators']['warning_count']]);
                fputcsv($handle, ['Status Evaluasi', $insights['risk_indicators']['all_normal'] ? 'Semua metrik dalam batas normal' : 'Perlu Mitigasi Manajemen']);
                fputcsv($handle, []);
                fputcsv($handle, ['No', 'ID Risiko', 'Tingkat', 'Judul Risiko', 'Deskripsi']);
                foreach ($insights['risk_indicators']['items'] as $idx => $risk) {
                    fputcsv($handle, [
                        $idx + 1,
                        $risk['id'],
                        $risk['severity_label'],
                        $risk['title'],
                        $risk['description'],
                    ]);
                }
                fputcsv($handle, []);
                fputcsv($handle, ['=== STATISTIK DETEKSI ANOMALI 30 HARI ===']);
                fputcsv($handle, ['Rata-rata Jam Harian (Mean)', $insights['anomaly_detection']['mean'].' jam']);
                fputcsv($handle, ['Batas Atas (+1 StdDev)', $insights['anomaly_detection']['upper_band'].' jam']);
                fputcsv($handle, ['Batas Bawah (-1 StdDev)', $insights['anomaly_detection']['lower_band'].' jam']);
                fputcsv($handle, ['Pola Anomali Terdeteksi', $insights['anomaly_detection']['unusual_patterns_count']]);
                fputcsv($handle, []);
                fputcsv($handle, ['Tanggal', 'Jam Lembur Aktual', 'Status Anomali']);
                foreach ($insights['anomaly_detection']['dates'] as $idx => $dateStr) {
                    $hours = $insights['anomaly_detection']['daily_hours'][$idx];
                    $isAnom = in_array($dateStr, array_column($insights['anomaly_detection']['anomalies'], 'date'), true);
                    fputcsv($handle, [
                        $dateStr,
                        $hours,
                        $isAnom ? 'ANOMALI TERDETEKSI' : 'Normal',
                    ]);
                }
                fputcsv($handle, []);
                fputcsv($handle, ['=== DAFTAR TINDAKAN MANAJEMEN (MANAGEMENT ACTION PLAN) ===']);
                fputcsv($handle, ['No', 'ID Tindakan', 'Prioritas', 'Tindakan Manajemen', 'Departemen', 'Dampak', 'Batas Waktu', 'Status', 'Catatan Resolusi']);
                foreach ($insights['action_items'] as $idx => $act) {
                    fputcsv($handle, [
                        $idx + 1,
                        $act['id'],
                        $act['priority_label'],
                        $act['action_item'],
                        $act['department'],
                        $act['impact'],
                        $act['deadline'],
                        $act['status_label'],
                        $act['resolution_note'] ?? '-',
                    ]);
                }
            } elseif ($tab === 'comparison') {
                $basePeriod = isset($params['base_period']) ? (string) $params['base_period'] : (isset($params['base']) ? (string) $params['base'] : substr($startDate, 0, 7));
                $comparePeriod = isset($params['compare_period']) ? (string) $params['compare_period'] : (isset($params['compare']) ? (string) $params['compare'] : null);
                $type = isset($params['type']) ? (string) $params['type'] : 'yoy';

                $comp = $this->comparisonService->getComparisonData($user, $departmentId, $basePeriod, $comparePeriod, $type);

                fputcsv($handle, ['=== INDIKATOR PERBANDINGAN PERIODE (E09-12) ===']);
                fputcsv($handle, ['Tipe Perbandingan', strtoupper($comp['comparison_type'])]);
                fputcsv($handle, ['Periode Basis', $comp['base_period_label']." ({$comp['base_period']})"]);
                fputcsv($handle, ['Periode Pembanding', $comp['compare_period_label']." ({$comp['compare_period']})"]);
                fputcsv($handle, []);

                fputcsv($handle, ['=== RINGKASAN METRIK PERBANDINGAN (KPI CARDS) ===']);
                fputcsv($handle, ['Metrik', 'Periode Basis', 'Periode Pembanding', 'Varians', 'Perubahan (%)', 'Arah Tren']);
                fputcsv($handle, [
                    'Total Jam Lembur',
                    $comp['kpi']['total_hours']['base_value'].' jam',
                    $comp['kpi']['total_hours']['compare_value'].' jam',
                    $comp['kpi']['total_hours']['formatted_diff'],
                    "{$comp['kpi']['total_hours']['pct_change']}%",
                    strtoupper($comp['kpi']['total_hours']['direction']),
                ]);
                fputcsv($handle, [
                    'Total Biaya Lembur',
                    $comp['kpi']['total_cost']['formatted_base'],
                    $comp['kpi']['total_cost']['formatted_compare'],
                    $comp['kpi']['total_cost']['formatted_diff'],
                    "{$comp['kpi']['total_cost']['pct_change']}%",
                    strtoupper($comp['kpi']['total_cost']['direction']),
                ]);
                fputcsv($handle, [
                    'Efisiensi Lembur (Jam/Unit)',
                    $comp['kpi']['efficiency']['base_hours_per_unit'].' jam/unit',
                    $comp['kpi']['efficiency']['compare_hours_per_unit'].' jam/unit',
                    $comp['kpi']['efficiency']['formatted_diff'],
                    "{$comp['kpi']['efficiency']['pct_change']}%",
                    strtoupper($comp['kpi']['efficiency']['direction']),
                ]);
                fputcsv($handle, [
                    'Headcount Lembur',
                    $comp['kpi']['headcount']['base_value'].' orang',
                    $comp['kpi']['headcount']['compare_value'].' orang',
                    $comp['kpi']['headcount']['formatted_diff'],
                    "{$comp['kpi']['headcount']['pct_change']}%",
                    strtoupper($comp['kpi']['headcount']['direction']),
                ]);
                fputcsv($handle, []);

                fputcsv($handle, ['=== BENCHMARKING DEPARTEMEN / SEKSI ===']);
                fputcsv($handle, ['No', 'Kode', 'Nama', 'Jam Basis', 'Jam Pembanding', 'Varians Jam', 'Varians (%)', 'Biaya Basis (Rp)', 'Biaya Pembanding (Rp)', 'Varians Biaya (%)', 'Burn Index (%)', 'Zona Burn']);
                foreach ($comp['department_benchmarks'] as $idx => $dept) {
                    fputcsv($handle, [
                        $idx + 1,
                        $dept['code'],
                        $dept['name'],
                        $dept['base_hours'],
                        $dept['compare_hours'],
                        $dept['variance_hours'],
                        "{$dept['variance_pct']}%",
                        number_format($dept['base_cost'], 0, ',', '.'),
                        number_format($dept['compare_cost'], 0, ',', '.'),
                        "{$dept['cost_variance_pct']}%",
                        "{$dept['burn_index_pct']}%",
                        strtoupper($dept['burn_zone']),
                    ]);
                }
                fputcsv($handle, []);

                fputcsv($handle, ['=== PRAKTIK TERBAIK & EVALUASI PERFORMA ===']);
                fputcsv($handle, ['Kategori', 'Judul Praktik Terbaik', 'Departemen', 'Metrik Kunci', 'Rincian Strategi']);
                foreach ($comp['best_practice_cards'] as $card) {
                    fputcsv($handle, [
                        $card['strategy_category'],
                        $card['title'],
                        $card['department_name'],
                        $card['metric_value'],
                        $card['description'],
                    ]);
                }
            } else {
                // Data section header
                fputcsv($handle, ['No', 'Indikator Analitik', 'Status / Nilai', 'Catatan Kebijakan']);
                fputcsv($handle, ['1', 'Status Modul', 'Aktif', 'Data terverifikasi']);
                fputcsv($handle, ['2', 'Cakupan Scope', $departmentName, 'Sesuai otorisasi']);
                fputcsv($handle, ['3', 'Periode Evaluasi', "{$startDate} s/d {$endDate}", 'Asia/Jakarta (WIB)']);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Export analytics summary as an executive PDF document.
     *
     * @param  array<string, mixed>  $params
     */
    protected function exportPdf(
        User $user,
        string $tab,
        string $departmentName,
        ?int $departmentId,
        string $startDate,
        string $endDate,
        string $filename,
        array $params = [],
    ): SymfonyResponse {
        $predictiveData = null;
        if ($tab === 'predictive') {
            $predictiveData = $this->predictiveService->getPredictiveData($user, $departmentId, $startDate, $endDate);
        }

        $costData = null;
        if ($tab === 'cost') {
            $costData = $this->costService->getCostData($user, $departmentId, $startDate, $endDate);
        }

        $correlationData = null;
        if ($tab === 'correlation') {
            $correlationData = $this->correlationService->getCorrelationData($user, $departmentId, $startDate, $endDate);
        }

        $insightsData = null;
        if ($tab === 'insights') {
            $insightsData = $this->insightService->getInsightsData($user, $departmentId, $startDate, $endDate);
        }

        $comparisonData = null;
        if ($tab === 'comparison') {
            $basePeriod = isset($params['base_period']) ? (string) $params['base_period'] : (isset($params['base']) ? (string) $params['base'] : substr($startDate, 0, 7));
            $comparePeriod = isset($params['compare_period']) ? (string) $params['compare_period'] : (isset($params['compare']) ? (string) $params['compare'] : null);
            $type = isset($params['type']) ? (string) $params['type'] : 'yoy';

            $comparisonData = $this->comparisonService->getComparisonData($user, $departmentId, $basePeriod, $comparePeriod, $type);
        }

        $data = [
            'title' => 'Ringkasan Eksekutif Analitik & Keputusan',
            'tab' => $tab,
            'tab_label' => self::TAB_LABELS[$tab] ?? $tab,
            'department_name' => $departmentName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'generated_by' => "{$user->name} ({$user->npk})",
            'generated_at' => Carbon::now('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB',
            'role_label' => $user->isAdmin() ? 'Administrator Pabrik' : 'Kepala Departemen (Manager)',
            'predictiveData' => $predictiveData,
            'costData' => $costData,
            'correlationData' => $correlationData,
            'insightsData' => $insightsData,
            'comparisonData' => $comparisonData,
        ];

        $pdf = Pdf::loadView('pdf.analytics-executive-summary', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
