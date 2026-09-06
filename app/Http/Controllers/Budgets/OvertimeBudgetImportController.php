<?php

namespace App\Http\Controllers\Budgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\ImportBudgetCsvRequest;
use App\Http\Requests\Budgets\PreviewBudgetCsvRequest;
use App\Models\User;
use App\Services\OvertimeBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class OvertimeBudgetImportController extends Controller
{
    public function __construct(
        public OvertimeBudgetService $budgetService,
    ) {}

    /**
     * Preview and dry-run validate uploaded CSV data without committing.
     */
    public function preview(PreviewBudgetCsvRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $file = $request->file('file') ?? $request->input('csv_content');
        $auditResult = $this->budgetService->parseAndValidateCsv($file, $user);

        return response()->json($auditResult);
    }

    /**
     * Commit validated rows to the database.
     */
    public function import(ImportBudgetCsvRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rows = $request->validated('rows');
        $result = $this->budgetService->importRows($rows, $user);

        if (! empty($result['errors']) && $result['imported'] === 0) {
            return redirect()->back()->withErrors([
                'import' => $result['errors'][0] ?? __('Failed to import overtime budgets.'),
            ]);
        }

        return redirect()->back()->with('success', __(':count budget record(s) imported successfully.', ['count' => $result['imported']]));
    }

    /**
     * Download standard CSV template.
     */
    public function template(): Response
    {
        $csvContent = $this->budgetService->generateCsvTemplate();

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="overtime_budget_template.csv"',
        ]);
    }
}
