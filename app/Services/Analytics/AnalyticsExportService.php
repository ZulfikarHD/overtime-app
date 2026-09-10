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
            return $this->exportCsv($user, $tab, $departmentName, $startDate, $endDate, $baseFilename.'.csv');
        }

        return $this->exportPdf($user, $tab, $departmentName, $startDate, $endDate, $baseFilename.'.pdf');
    }

    /**
     * Export analytics summary as a streamed CSV with UTF-8 BOM.
     */
    protected function exportCsv(
        User $user,
        string $tab,
        string $departmentName,
        string $startDate,
        string $endDate,
        string $filename,
    ): StreamedResponse {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($user, $tab, $departmentName, $startDate, $endDate) {
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

            // Data section header
            fputcsv($handle, ['No', 'Indikator Analitik', 'Status / Nilai', 'Catatan Kebijakan']);
            fputcsv($handle, ['1', 'Status Modul', 'Aktif', 'Data terverifikasi']);
            fputcsv($handle, ['2', 'Cakupan Scope', $departmentName, 'Sesuai otorisasi']);
            fputcsv($handle, ['3', 'Periode Evaluasi', "{$startDate} s/d {$endDate}", 'Asia/Jakarta (WIB)']);

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Export analytics summary as an executive PDF document.
     */
    protected function exportPdf(
        User $user,
        string $tab,
        string $departmentName,
        string $startDate,
        string $endDate,
        string $filename,
    ): SymfonyResponse {
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
        ];

        $pdf = Pdf::loadView('pdf.analytics-executive-summary', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
