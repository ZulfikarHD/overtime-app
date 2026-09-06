<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportEmployeeCsvRequest;
use App\Http\Requests\Admin\PreviewEmployeeCsvRequest;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class EmployeeImportController extends Controller
{
    public function __construct(
        public EmployeeService $employeeService,
    ) {}

    /**
     * Preview and dry-run validate uploaded CSV data without committing.
     */
    public function preview(PreviewEmployeeCsvRequest $request): JsonResponse
    {
        $file = $request->file('file') ?? $request->input('csv_content');
        $auditResult = $this->employeeService->parseAndValidateCsv($file);

        return response()->json($auditResult);
    }

    /**
     * Commit validated rows to the database.
     */
    public function import(ImportEmployeeCsvRequest $request): RedirectResponse
    {
        $rows = $request->validated('rows');
        $result = $this->employeeService->importRows($rows);

        if (! empty($result['errors'])) {
            return redirect()->back()->withErrors([
                'import' => $result['errors'][0] ?? __('Failed to import employees.'),
            ]);
        }

        return redirect()->back()->with('success', __(':count employee(s) imported successfully.', ['count' => $result['imported']]));
    }

    /**
     * Download standard CSV template.
     */
    public function template(): Response
    {
        $csvContent = $this->employeeService->generateCsvTemplate();

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employee_roster_template.csv"',
        ]);
    }
}
