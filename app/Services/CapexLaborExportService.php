<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ExportLog;
use App\Models\OvertimeItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;
use ZipArchive;

class CapexLaborExportService
{
    /**
     * Standard 13 columns for CapEx labor attribution schedule (PSAK 16 / IAS 16).
     *
     * @var list<string>
     */
    public const HEADINGS = [
        'Kode Proyek',
        'Nama Proyek',
        'Kode Aset Tetap',
        'Departemen',
        'Tanggal Operasional (WIB)',
        'NPK',
        'Nama Karyawan',
        'Jam Lembur Proyek',
        'Tarif Snapshot/Jam (Rp)',
        'Total Biaya Terkapitalisasi (Rp)',
        'No. SPKL',
        'Tanggal Persetujuan (WIB)',
        'Disetujui Oleh',
    ];

    public function __construct(
        public CapExAccountingService $capexAccountingService,
    ) {}

    /**
     * Export CapEx labor attribution schedule to Excel (.xlsx) or CSV.
     *
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters, User $user, string $format = 'xlsx'): Response
    {
        $normalizedFormat = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $filename = $this->generateFilename($user, $filters, $normalizedFormat);

        /** @var LazyCollection<int, OvertimeItem> $cursor */
        $cursor = $this->capexAccountingService->buildAttributionQuery($filters, $user)->cursor();

        if ($normalizedFormat === 'csv') {
            return $this->downloadCsv($cursor, $filename, $user, $filters);
        }

        return $this->downloadXlsx($cursor, $filename, $user, $filters);
    }

    /**
     * Stream native OpenXML (.xlsx) using ZipArchive and XMLWriter with project subtotals and grand totals.
     *
     * @param  LazyCollection<int, OvertimeItem>  $cursor
     * @param  array<string, mixed>  $filters
     */
    public function downloadXlsx(LazyCollection $cursor, string $filename, User $user, array $filters): BinaryFileResponse
    {
        $tempXlsxPath = tempnam(sys_get_temp_dir(), 'cpx_attr_').'.xlsx';

        $zip = new ZipArchive;
        $zip->open($tempXlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Standard OpenXML package structure
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Atribusi CapEx" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        // Styles: index 0 = normal font, index 1 = bold font
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Calibri"/><sz val="11"/></font>
    <font><b/><name val="Calibri"/><sz val="11"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>');

        // Stream worksheet data into temporary XML file
        $tempSheetPath = tempnam(sys_get_temp_dir(), 'cpx_sheet_');
        $writer = new XMLWriter;
        $writer->openUri($tempSheetPath);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('worksheet');
        $writer->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $writer->startElement('sheetData');

        // Row 1: Headings (Bold)
        $writer->startElement('row');
        $writer->writeAttribute('r', '1');

        foreach (self::HEADINGS as $colIdx => $heading) {
            $colLetter = chr(65 + $colIdx);
            $writer->startElement('c');
            $writer->writeAttribute('r', $colLetter.'1');
            $writer->writeAttribute('t', 'inlineStr');
            $writer->writeAttribute('s', '1');
            $writer->startElement('is');
            $writer->writeElement('t', $heading);
            $writer->endElement(); // is
            $writer->endElement(); // c
        }
        $writer->endElement(); // row 1

        // Rows 2..N: Data rows grouped by project with subtotals
        $rowNumber = 2;
        $currentProjectId = null;
        $currentProjectCode = '';
        $projectSubtotalHours = 0.0;
        $projectSubtotalCost = 0.0;
        $grandTotalHours = 0.0;
        $grandTotalCost = 0.0;
        $recordCount = 0;

        foreach ($cursor as $item) {
            $recordCount++;

            // If switching project group, emit subtotal row for prior project
            if ($currentProjectId !== null && $item->capex_project_id !== $currentProjectId) {
                $this->writeXlsxSubtotalRow($writer, $rowNumber, $currentProjectCode, $projectSubtotalHours, $projectSubtotalCost);
                $rowNumber++;

                $projectSubtotalHours = 0.0;
                $projectSubtotalCost = 0.0;
            }

            $currentProjectId = $item->capex_project_id;
            $currentProjectCode = $item->capexProject?->project_code ?? '';

            $hours = (float) $item->hours_project;
            $rate = (float) $item->hourly_rate_snapshot;
            $cost = (float) $item->total_cost_snapshot;

            $projectSubtotalHours += $hours;
            $projectSubtotalCost += $cost;
            $grandTotalHours += $hours;
            $grandTotalCost += $cost;

            $rowValues = [
                0 => ['val' => $item->capexProject?->project_code ?? '', 'type' => 'inlineStr'],
                1 => ['val' => $item->capexProject?->name ?? '', 'type' => 'inlineStr'],
                2 => ['val' => $item->capexProject?->asset_code ?? '', 'type' => 'inlineStr'],
                3 => ['val' => $item->capexProject?->department?->name ?? $item->overtimeSubmission?->department?->name ?? '', 'type' => 'inlineStr'],
                4 => ['val' => $item->overtimeSubmission?->operational_date?->format('Y-m-d') ?? '', 'type' => 'inlineStr'],
                5 => ['val' => $item->npk_snapshot ?: ($item->employee?->npk ?? ''), 'type' => 'inlineStr'],
                6 => ['val' => $item->employee?->full_name ?? '', 'type' => 'inlineStr'],
                7 => ['val' => number_format($hours, 2, '.', ''), 'type' => 'n'],
                8 => ['val' => number_format($rate, 2, '.', ''), 'type' => 'n'],
                9 => ['val' => number_format($cost, 2, '.', ''), 'type' => 'n'],
                10 => ['val' => $item->overtimeSubmission?->submission_code ?? '', 'type' => 'inlineStr'],
                11 => ['val' => $item->reviewed_at ? Carbon::parse($item->reviewed_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') : '', 'type' => 'inlineStr'],
                12 => ['val' => $item->reviewedBy?->name ?? 'System', 'type' => 'inlineStr'],
            ];

            $writer->startElement('row');
            $writer->writeAttribute('r', (string) $rowNumber);

            foreach ($rowValues as $colIdx => $colMeta) {
                $colLetter = chr(65 + $colIdx);
                $cellRef = $colLetter.$rowNumber;

                $writer->startElement('c');
                $writer->writeAttribute('r', $cellRef);

                if ($colMeta['type'] === 'n') {
                    $writer->writeAttribute('t', 'n');
                    $writer->writeElement('v', $colMeta['val']);
                } elseif ($colMeta['val'] !== '') {
                    $writer->writeAttribute('t', 'inlineStr');
                    $writer->startElement('is');
                    $writer->writeElement('t', (string) $colMeta['val']);
                    $writer->endElement();
                }

                $writer->endElement(); // c
            }

            $writer->endElement(); // row
            $rowNumber++;
        }

        // Final project group subtotal (if any items processed)
        if ($currentProjectId !== null) {
            $this->writeXlsxSubtotalRow($writer, $rowNumber, $currentProjectCode, $projectSubtotalHours, $projectSubtotalCost);
            $rowNumber++;
        }

        // Grand Total row at report bottom
        $this->writeXlsxGrandTotalRow($writer, $rowNumber, $grandTotalHours, $grandTotalCost);

        $writer->endElement(); // sheetData
        $writer->endElement(); // worksheet
        $writer->endDocument();
        $writer->flush();

        $zip->addFile($tempSheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tempSheetPath);

        // Record audit trail
        ExportLog::create([
            'actor_user_id' => $user->id,
            'resource_type' => 'capex_labor_attribution',
            'format' => 'xlsx',
            'filename' => $filename,
            'record_count' => $recordCount,
            'filters' => $filters,
            'ip_address' => request()?->ip(),
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return response()->download(
            $tempXlsxPath,
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        )->deleteFileAfterSend(true);
    }

    /**
     * Stream CSV with UTF-8 BOM, project subtotals, and grand total.
     *
     * @param  LazyCollection<int, OvertimeItem>  $cursor
     * @param  array<string, mixed>  $filters
     */
    public function downloadCsv(LazyCollection $cursor, string $filename, User $user, array $filters): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($cursor, $user, $filename, $filters) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, self::HEADINGS);

            $currentProjectId = null;
            $currentProjectCode = '';
            $projectSubtotalHours = 0.0;
            $projectSubtotalCost = 0.0;
            $grandTotalHours = 0.0;
            $grandTotalCost = 0.0;
            $recordCount = 0;

            foreach ($cursor as $item) {
                $recordCount++;

                if ($currentProjectId !== null && $item->capex_project_id !== $currentProjectId) {
                    // Write subtotal row
                    fputcsv($handle, [
                        'Subtotal Proyek: '.$currentProjectCode,
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        number_format($projectSubtotalHours, 2, '.', ''),
                        '',
                        number_format($projectSubtotalCost, 2, '.', ''),
                        '',
                        '',
                        '',
                    ]);

                    $projectSubtotalHours = 0.0;
                    $projectSubtotalCost = 0.0;
                }

                $currentProjectId = $item->capex_project_id;
                $currentProjectCode = $item->capexProject?->project_code ?? '';

                $hours = (float) $item->hours_project;
                $rate = (float) $item->hourly_rate_snapshot;
                $cost = (float) $item->total_cost_snapshot;

                $projectSubtotalHours += $hours;
                $projectSubtotalCost += $cost;
                $grandTotalHours += $hours;
                $grandTotalCost += $cost;

                fputcsv($handle, [
                    $item->capexProject?->project_code ?? '',
                    $item->capexProject?->name ?? '',
                    $item->capexProject?->asset_code ?? '',
                    $item->capexProject?->department?->name ?? $item->overtimeSubmission?->department?->name ?? '',
                    $item->overtimeSubmission?->operational_date?->format('Y-m-d') ?? '',
                    $item->npk_snapshot ?: ($item->employee?->npk ?? ''),
                    $item->employee?->full_name ?? '',
                    number_format($hours, 2, '.', ''),
                    number_format($rate, 2, '.', ''),
                    number_format($cost, 2, '.', ''),
                    $item->overtimeSubmission?->submission_code ?? '',
                    $item->reviewed_at ? Carbon::parse($item->reviewed_at)->setTimezone('Asia/Jakarta')->format('Y-m-d H:i') : '',
                    $item->reviewedBy?->name ?? 'System',
                ]);
            }

            if ($currentProjectId !== null) {
                fputcsv($handle, [
                    'Subtotal Proyek: '.$currentProjectCode,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    number_format($projectSubtotalHours, 2, '.', ''),
                    '',
                    number_format($projectSubtotalCost, 2, '.', ''),
                    '',
                    '',
                    '',
                ]);
            }

            // Grand Total
            fputcsv($handle, [
                'Grand Total Seluruh Proyek',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format($grandTotalHours, 2, '.', ''),
                '',
                number_format($grandTotalCost, 2, '.', ''),
                '',
                '',
                '',
            ]);

            fclose($handle);

            ExportLog::create([
                'actor_user_id' => $user->id,
                'resource_type' => 'capex_labor_attribution',
                'format' => 'csv',
                'filename' => $filename,
                'record_count' => $recordCount,
                'filters' => $filters,
                'ip_address' => request()?->ip(),
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        }, 200, $headers);
    }

    /**
     * Write an XLSX project subtotal row.
     */
    protected function writeXlsxSubtotalRow(
        XMLWriter $writer,
        int $rowNumber,
        string $projectCode,
        float $subtotalHours,
        float $subtotalCost
    ): void {
        $writer->startElement('row');
        $writer->writeAttribute('r', (string) $rowNumber);

        // Col A: Subtotal Label (Bold)
        $writer->startElement('c');
        $writer->writeAttribute('r', 'A'.$rowNumber);
        $writer->writeAttribute('t', 'inlineStr');
        $writer->writeAttribute('s', '1');
        $writer->startElement('is');
        $writer->writeElement('t', 'Subtotal Proyek: '.$projectCode);
        $writer->endElement();
        $writer->endElement();

        // Col H: Subtotal Hours (Bold, numeric)
        $writer->startElement('c');
        $writer->writeAttribute('r', 'H'.$rowNumber);
        $writer->writeAttribute('t', 'n');
        $writer->writeAttribute('s', '1');
        $writer->writeElement('v', number_format($subtotalHours, 2, '.', ''));
        $writer->endElement();

        // Col J: Subtotal Cost (Bold, numeric)
        $writer->startElement('c');
        $writer->writeAttribute('r', 'J'.$rowNumber);
        $writer->writeAttribute('t', 'n');
        $writer->writeAttribute('s', '1');
        $writer->writeElement('v', number_format($subtotalCost, 2, '.', ''));
        $writer->endElement();

        $writer->endElement(); // row
    }

    /**
     * Write an XLSX grand total row.
     */
    protected function writeXlsxGrandTotalRow(
        XMLWriter $writer,
        int $rowNumber,
        float $grandTotalHours,
        float $grandTotalCost
    ): void {
        $writer->startElement('row');
        $writer->writeAttribute('r', (string) $rowNumber);

        // Col A: Grand Total Label (Bold)
        $writer->startElement('c');
        $writer->writeAttribute('r', 'A'.$rowNumber);
        $writer->writeAttribute('t', 'inlineStr');
        $writer->writeAttribute('s', '1');
        $writer->startElement('is');
        $writer->writeElement('t', 'Grand Total Seluruh Proyek');
        $writer->endElement();
        $writer->endElement();

        // Col H: Grand Total Hours (Bold, numeric)
        $writer->startElement('c');
        $writer->writeAttribute('r', 'H'.$rowNumber);
        $writer->writeAttribute('t', 'n');
        $writer->writeAttribute('s', '1');
        $writer->writeElement('v', number_format($grandTotalHours, 2, '.', ''));
        $writer->endElement();

        // Col J: Grand Total Cost (Bold, numeric)
        $writer->startElement('c');
        $writer->writeAttribute('r', 'J'.$rowNumber);
        $writer->writeAttribute('t', 'n');
        $writer->writeAttribute('s', '1');
        $writer->writeElement('v', number_format($grandTotalCost, 2, '.', ''));
        $writer->endElement();

        $writer->endElement(); // row
    }

    /**
     * Generate the standardized filename: Laporan-Atribusi-CapEx-{dept}-{period}.{format}
     *
     * @param  array<string, mixed>  $filters
     */
    public function generateFilename(User $user, array $filters, string $format = 'xlsx'): string
    {
        $deptSegment = 'SEMUA';
        if ($user->isManager() && $user->department) {
            $deptSegment = Str::slug($user->department->code ?: $user->department->name);
        } elseif (! empty($filters['department_id'])) {
            $dept = Department::find($filters['department_id']);
            if ($dept) {
                $deptSegment = Str::slug($dept->code ?: $dept->name);
            }
        }

        $dateSegment = Carbon::now('Asia/Jakarta')->format('Y-m');
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $dateSegment = $filters['date_from'].'_sd_'.$filters['date_to'];
        } elseif (! empty($filters['date_from'])) {
            $dateSegment = 'sejak_'.$filters['date_from'];
        }

        return "Laporan-Atribusi-CapEx-{$deptSegment}-{$dateSegment}.{$format}";
    }
}
