<?php

namespace App\Http\Controllers\Budgets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\StoreOvertimeBudgetRequest;
use App\Models\User;
use App\Services\OvertimeBudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeBudgetController extends Controller
{
    public function __construct(
        public OvertimeBudgetService $budgetService,
    ) {}

    /**
     * Display the Overtime Budget Planning matrix hub.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $year = $request->integer('year', (int) date('Y'));
        $month = $request->integer('month', (int) date('n'));
        $departmentId = $request->has('department_id') && $request->input('department_id') !== ''
            ? $request->integer('department_id')
            : null;

        $matrix = $this->budgetService->getPlanningMatrix($year, $month, $departmentId, $user);

        return Inertia::render('budgets/Planning', $matrix);
    }

    /**
     * Store or update an overtime budget allocation.
     */
    public function store(StoreOvertimeBudgetRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->budgetService->upsertBudget($request->validated(), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Overtime budget plan saved successfully.'),
        ]);

        return redirect()->back();
    }
}
