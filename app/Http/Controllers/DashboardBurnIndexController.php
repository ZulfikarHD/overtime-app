<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Analytics\MonthlySnapshotService;
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
}
