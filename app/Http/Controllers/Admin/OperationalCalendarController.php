<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportCalendarCsvRequest;
use App\Http\Requests\Admin\PreviewCalendarCsvRequest;
use App\Http\Requests\Admin\UpdateCalendarDayRequest;
use App\Services\OperationalCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class OperationalCalendarController extends Controller
{
    public function __construct(
        public OperationalCalendarService $calendarService,
    ) {}

    /**
     * Update an operational calendar date classification.
     */
    public function update(UpdateCalendarDayRequest $request, string $date): RedirectResponse
    {
        $this->calendarService->updateDate($date, $request->validated());

        return redirect()->back()->with(
            'success',
            __('Kalender operasional untuk tanggal :date berhasil diperbarui.', ['date' => $date])
        );
    }

    /**
     * Preview and dry-run validate uploaded national holidays CSV.
     */
    public function preview(PreviewCalendarCsvRequest $request): JsonResponse
    {
        $file = $request->file('file') ?? $request->input('csv_content');
        $auditResult = $this->calendarService->parseAndValidateCsv($file);

        return response()->json($auditResult);
    }

    /**
     * Commit validated national holidays CSV rows.
     */
    public function import(ImportCalendarCsvRequest $request): RedirectResponse
    {
        $rows = $request->validated('rows');
        $result = $this->calendarService->importRows($rows);

        if (! empty($result['errors'])) {
            return redirect()->back()->withErrors([
                'import' => $result['errors'][0] ?? __('Gagal mengimport hari libur nasional.'),
            ]);
        }

        return redirect()->back()->with(
            'success',
            __(':count hari libur nasional berhasil diimport ke kalender operasional.', ['count' => $result['imported']])
        );
    }

    /**
     * Download national holidays CSV template.
     */
    public function template(): Response
    {
        $csvContent = $this->calendarService->generateCsvTemplate();

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="national_holidays_template.csv"',
        ]);
    }
}
