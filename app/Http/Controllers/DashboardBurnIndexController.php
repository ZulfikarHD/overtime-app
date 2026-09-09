<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\MonthlySnapshotService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DashboardBurnIndexController extends Controller
{
    public function __construct(
        public MonthlySnapshotService $snapshotService,
    ) {}

    /**
     * Display the Section-Level Burn Index Dashboard.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $now = Carbon::now('Asia/Jakarta');
        $year = $request->integer('year', (int) $now->format('Y'));
        $month = $request->integer('month', (int) $now->format('n'));

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '') {
            $departmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 0 : (int) $rawDept;
        }

        $tab = $request->string('tab', 'sections')->value();
        $rangeType = $request->string('range_type', 'month')->value();
        $startDate = $request->filled('start_date') ? $request->string('start_date')->value() : null;
        $endDate = $request->filled('end_date') ? $request->string('end_date')->value() : null;

        $data = $this->snapshotService->getDashboardData(
            $user,
            $year,
            $month,
            $departmentId,
            $tab,
            $rangeType,
            $startDate,
            $endDate,
        );

        return Inertia::render('dashboard/BurnIndex', $data);
    }

    /**
     * Generate and stream executive PDF reports (Weekly Standup or Monthly Closing).
     */
    public function exportPdf(Request $request): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isManager() || $user->isTeamLeader(), 403);

        $now = Carbon::now('Asia/Jakarta');
        $year = $request->integer('year', (int) $now->format('Y'));
        $month = $request->integer('month', (int) $now->format('n'));

        $rawDept = $request->input('department_id');
        $departmentId = null;
        if ($request->has('department_id') && $rawDept !== '') {
            $departmentId = ($rawDept === 'all' || (int) $rawDept === 0) ? 0 : (int) $rawDept;
        }

        if ($user->isManager() || $user->isTeamLeader()) {
            $departmentId = (int) $user->department_id;
        }

        $reportType = $request->string('type', 'standup')->value();
        if (! in_array($reportType, ['standup', 'monthly'], true)) {
            $reportType = 'standup';
        }

        $pdfData = $this->snapshotService->getPdfExportData($user, $year, $month, $departmentId, $reportType);

        $viewName = $reportType === 'monthly' ? 'pdf.burn-index-monthly' : 'pdf.burn-index-standup';

        $pdf = Pdf::loadView($viewName, $pdfData);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($pdfData['filename']);
    }

    /**
     * Force on-demand snapshot recalculation for the selected department and period.
     */
    public function recalculate(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isManager(), 403);

        $now = Carbon::now('Asia/Jakarta');
        $year = $request->integer('year', (int) $now->format('Y'));
        $month = $request->integer('month', (int) $now->format('n'));

        $departmentId = $user->isManager()
            ? (int) $user->department_id
            : ($request->has('department_id') && $request->input('department_id') !== '' ? $request->integer('department_id') : null);

        $this->snapshotService->recalculateAll($year, $month, $departmentId);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Indeks burn berhasil diperbarui.'),
        ]);

        return redirect()->back();
    }

    /**
     * Display or return weekly burndown analytics for a specific section.
     */
    public function show(Request $request, Section $section): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Role-based scoping check
        if ($user->isTeamLeader()) {
            abort_unless((int) $user->section_id === (int) $section->id, 403);
        } elseif ($user->isManager()) {
            abort_unless((int) $user->department_id === (int) $section->department_id, 403);
        } elseif (! $user->isAdmin()) {
            abort(403);
        }

        $now = Carbon::now('Asia/Jakarta');
        $year = $request->integer('year', (int) $now->format('Y'));
        $month = $request->integer('month', (int) $now->format('n'));

        // If request is from browser navigation (not AJAX/Inertia partial/JSON), redirect to hub with deep link query
        if (! $request->wantsJson() && ! $request->ajax()) {
            return redirect()->route('dashboard.burn-index', [
                'tab' => 'sections',
                'section' => $section->id,
                'year' => $year,
                'month' => $month,
                'department_id' => $section->department_id,
            ]);
        }

        $data = $this->snapshotService->getWeeklyBurndown($section, $year, $month);

        return response()->json($data);
    }
}
