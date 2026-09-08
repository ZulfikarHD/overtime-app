<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ExportLog;
use App\Models\OvertimeItem;
use App\Models\OvertimeItemAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;
use ZipArchive;

class OvertimeExportService
{
    /**
     * Standard 19 columns required by E04-04 specification.
     *
     * @var list<string>
     */
    public const HEADINGS = [
        'submission_code',
        'operational_date',
        'day_type',
        'department',
        'section',
        'npk',
        'employee_name',
        'hours_production',
        'hours_tpm',
        'hours_project',
        'hours_others',
        'total_hours',
        'hourly_rate_snapshot',
        'total_cost_idr',
        'rca_category',
        'status',
        'rejection_reason',
        'capex_project_code',
        'spkl_status',
    ];

    /**
     * List of numeric column keys for Excel formatting.
     *
     * @var list<string>
     */
    public const NUMERIC_COLUMNS = [
        'hours_production',
        'hours_tpm',
        'hours_project',
        'hours_others',
        'total_hours',
        'hourly_rate_snapshot',
        'total_cost_idr',
    ];

    /**
     * Build the filtered, scoped Eloquent query for export.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<OvertimeItem>
     */
    public function buildQuery(array $filters, User $user): Builder
    {
        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();

        /** @var Builder<OvertimeItem> $query */
        $query = OvertimeItem::query()
            ->with([
                'overtimeSubmission' => function ($sq) {
                    $sq->with(['department:id,name,code', 'section:id,name,code', 'spklDocument']);
                },
                'employee:id,npk,full_name,job_position',
                'capexProject:id,project_code,name',
            ]);

        // Role-based Department Scoping:
        // Manager is strictly restricted to their department
        if ($user->isManager() && $user->department_id) {
            $query->whereHas('overtimeSubmission', function (Builder $sq) use ($user) {
                $sq->where('department_id', $user->department_id);
            });
        } elseif ($user->isAdmin() && ! empty($filters['department_id'])) {
            $filterDeptId = (int) $filters['department_id'];
            $query->whereHas('overtimeSubmission', function (Builder $sq) use ($filterDeptId) {
                $sq->where('department_id', $filterDeptId);
            });
        }

        // Section Scoping
        if (! empty($filters['section_id'])) {
            $filterSecId = (int) $filters['section_id'];
            if ($user->canAccessSection($filterSecId)) {
                $query->whereHas('overtimeSubmission', function (Builder $sq) use ($filterSecId) {
                    $sq->where('section_id', $filterSecId);
                });
            }
        }

        // Date Range Filtering
        $dateFrom = ! empty($filters['date_from']) ? (string) $filters['date_from'] : null;
        $dateTo = ! empty($filters['date_to']) ? (string) $filters['date_to'] : null;

        if ($dateFrom) {
            $query->whereHas('overtimeSubmission', function (Builder $sq) use ($dateFrom) {
                $sq->whereDate('operational_date', '>=', $dateFrom);
            });
        }

        if ($dateTo) {
            $query->whereHas('overtimeSubmission', function (Builder $sq) use ($dateTo) {
                $sq->whereDate('operational_date', '<=', $dateTo);
            });
        }

        // Status Filtering
        $statusInput = $filters['status'] ?? null;
        if ($statusInput !== null && $statusInput !== '' && $statusInput !== 'ALL') {
            if ($statusInput === 'SUBMITTED') {
                $query->whereHas('overtimeSubmission', function (Builder $sq) {
                    $sq->where('status', 'SUBMITTED');
                });
            } elseif ($statusInput === 'PENDING') {
                $query->whereHas('overtimeSubmission', function (Builder $sq) {
                    $sq->whereIn('status', ['SUBMITTED', 'PARTIALLY_APPROVED']);
                });
            } elseif ($statusInput === 'PARTIALLY_APPROVED') {
                $query->whereHas('overtimeSubmission', function (Builder $sq) {
                    $sq->where('status', 'PARTIALLY_APPROVED');
                });
            } elseif (in_array(strtoupper($statusInput), ['APPROVED', 'REJECTED'], true)) {
                $query->where('status', strtoupper($statusInput));
            } else {
                $query->whereHas('overtimeSubmission', function (Builder $sq) use ($statusInput) {
                    $sq->where('status', $statusInput);
                });
            }
        }

        // SPKL Status Filtering
        if (! empty($filters['spkl_status'])) {
            $spklStatus = (string) $filters['spkl_status'];
            if ($spklStatus === 'OVERDUE') {
                $query->whereHas('overtimeSubmission.spklDocument', function (Builder $q) use ($todayWib) {
                    $q->where('status', 'PENDING')
                        ->whereDate('due_date', '<', $todayWib);
                });
            } elseif ($spklStatus === 'NONE') {
                $query->whereDoesntHave('overtimeSubmission.spklDocument');
            } elseif (in_array($spklStatus, ['PENDING', 'ATTACHED', 'VERIFIED'], true)) {
                $query->whereHas('overtimeSubmission.spklDocument', function (Builder $q) use ($spklStatus) {
                    $q->where('status', $spklStatus);
                });
            }
        }

        $query->orderBy('overtime_items.id', 'asc');

        return $query;
    }

    /**
     * Map a single OvertimeItem model to an associative array matching the 19 standard columns.
     *
     * @return array<string, mixed>
     */
    public function mapItem(OvertimeItem $item): array
    {
        $submission = $item->overtimeSubmission;
        $doc = $submission?->spklDocument;
        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();

        $spklStatus = 'NONE';
        if ($doc) {
            if ($doc->status === 'PENDING' && $doc->due_date && $doc->due_date < $todayWib) {
                $spklStatus = 'OVERDUE';
            } else {
                $spklStatus = $doc->status;
            }
        }

        return [
            'submission_code' => $submission?->submission_code ?? '',
            'operational_date' => $submission?->operational_date?->format('Y-m-d') ?? '',
            'day_type' => $submission?->day_type ?? '',
            'department' => $submission?->department?->name ?? '',
            'section' => $submission?->section?->name ?? '',
            'npk' => $item->npk_snapshot ?: ($item->employee?->npk ?? ''),
            'employee_name' => $item->employee?->full_name ?? '',
            'hours_production' => number_format((float) $item->hours_production, 2, '.', ''),
            'hours_tpm' => number_format((float) $item->hours_tpm, 2, '.', ''),
            'hours_project' => number_format((float) $item->hours_project, 2, '.', ''),
            'hours_others' => number_format((float) $item->hours_others, 2, '.', ''),
            'total_hours' => number_format((float) $item->total_hours, 2, '.', ''),
            'hourly_rate_snapshot' => number_format((float) $item->hourly_rate_snapshot, 2, '.', ''),
            'total_cost_idr' => number_format((float) $item->total_cost_snapshot, 2, '.', ''),
            'rca_category' => $item->rca_category ?? '',
            'status' => $item->status,
            'rejection_reason' => $item->rejection_reason ?? '',
            'capex_project_code' => $item->capexProject?->project_code ?? '',
            'spkl_status' => $spklStatus,
        ];
    }

    /**
     * Generate the standardized filename: overtime-export-{department}-{YYYY-MM}.{format}
     *
     * @param  array<string, mixed>  $filters
     */
    public function generateFilename(User $user, array $filters, string $format): string
    {
        $deptSegment = 'all';

        if ($user->isManager() && $user->department_id) {
            $user->loadMissing('department');
            $deptSegment = Str::slug($user->department?->name ?? $user->department?->code ?? 'dept');
        } elseif (! empty($filters['department_id'])) {
            $dept = Department::find((int) $filters['department_id']);
            if ($dept) {
                $deptSegment = Str::slug($dept->name ?: $dept->code);
            }
        }

        $dateSegment = Carbon::now('Asia/Jakarta')->format('Y-m');
        if (! empty($filters['date_from'])) {
            try {
                $dateSegment = Carbon::parse((string) $filters['date_from'])->format('Y-m');
            } catch (\Throwable) {
                // Keep default
            }
        }

        $ext = strtolower($format) === 'xlsx' ? 'xlsx' : 'csv';

        return "overtime-export-{$deptSegment}-{$dateSegment}.{$ext}";
    }

    /**
     * Execute export and return streamed response (CSV or XLSX) with audit logging.
     *
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters, User $user, string $format = 'csv'): Response
    {
        $format = strtolower($format) === 'xlsx' ? 'xlsx' : 'csv';
        $filename = $this->generateFilename($user, $filters, $format);

        $query = $this->buildQuery($filters, $user);
        $recordCount = (clone $query)->count();

        // 1. Log export in dedicated export_logs table
        ExportLog::create([
            'actor_user_id' => $user->id,
            'resource_type' => 'overtime_approvals',
            'format' => $format,
            'filename' => $filename,
            'record_count' => $recordCount,
            'filters' => $filters,
            'ip_address' => request()?->ip(),
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        // 2. Log export in immutable audit ledger (OvertimeItemAudit)
        OvertimeItemAudit::create([
            'overtime_item_id' => null,
            'action' => 'EXPORT',
            'actor_user_id' => $user->id,
            'previous_state' => null,
            'new_state' => [
                'format' => $format,
                'filename' => $filename,
                'record_count' => $recordCount,
                'filters' => $filters,
            ],
            'notes' => __('Export data lembur :format (:filename) total :count baris.', [
                'format' => strtoupper($format),
                'filename' => $filename,
                'count' => $recordCount,
            ]),
            'ip_address' => request()?->ip(),
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        if ($format === 'xlsx') {
            return $this->downloadXlsx($query->cursor(), $filename);
        }

        return $this->streamCsv($query->cursor(), $filename);
    }

    /**
     * Stream CSV output using LazyCollection cursor to avoid memory bloat.
     *
     * @param  LazyCollection<int, OvertimeItem>  $cursor
     */
    public function streamCsv(LazyCollection $cursor, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($cursor) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // Write UTF-8 BOM so Microsoft Excel correctly displays Indonesian characters and symbols
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write Header
            fputcsv($handle, self::HEADINGS);

            // Stream items row-by-row
            foreach ($cursor as $item) {
                $row = $this->mapItem($item);
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Generate native OpenXML (.xlsx) using ZipArchive & XMLWriter with cursor streaming.
     *
     * @param  LazyCollection<int, OvertimeItem>  $cursor
     */
    public function downloadXlsx(LazyCollection $cursor, string $filename): BinaryFileResponse
    {
        $tempXlsxPath = tempnam(sys_get_temp_dir(), 'ot_export_').'.xlsx';

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
    <sheet name="Overtime" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>');

        // Styles: index 0 = normal, index 1 = bold header
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

        // Stream worksheet data into temporary sheet XML file
        $tempSheetPath = tempnam(sys_get_temp_dir(), 'ot_sheet_');
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

        // Rows 2..N: Data rows
        $rowNumber = 2;
        foreach ($cursor as $item) {
            $mapped = $this->mapItem($item);

            $writer->startElement('row');
            $writer->writeAttribute('r', (string) $rowNumber);

            $colIdx = 0;
            foreach ($mapped as $key => $val) {
                $colLetter = chr(65 + $colIdx);
                $cellRef = $colLetter.$rowNumber;

                $isNumeric = in_array($key, self::NUMERIC_COLUMNS, true);

                $writer->startElement('c');
                $writer->writeAttribute('r', $cellRef);

                if ($isNumeric && is_numeric($val)) {
                    $writer->writeAttribute('t', 'n');
                    $writer->writeElement('v', (string) $val);
                } elseif ($val === '' || $val === null) {
                    // Empty cell
                } else {
                    $writer->writeAttribute('t', 'inlineStr');
                    $writer->startElement('is');
                    $writer->writeElement('t', (string) $val);
                    $writer->endElement();
                }

                $writer->endElement(); // c
                $colIdx++;
            }

            $writer->endElement(); // row
            $rowNumber++;
        }

        $writer->endElement(); // sheetData
        $writer->endElement(); // worksheet
        $writer->endDocument();
        $writer->flush();

        $zip->addFile($tempSheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tempSheetPath);

        return response()->download($tempXlsxPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ])->deleteFileAfterSend(true);
    }
}
