<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\MonthlySnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

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
        $departmentId = $request->has('department_id') && $request->input('department_id') !== ''
            ? $request->integer('department_id')
            : null;
        $tab = $request->string('tab', 'sections')->value();

        $data = $this->snapshotService->getDashboardData($user, $year, $month, $departmentId, $tab);

        return Inertia::render('dashboard/BurnIndex', $data);
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
