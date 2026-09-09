<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCapexProjectRequest;
use App\Http\Requests\Admin\UpdateCapexProjectProgressRequest;
use App\Http\Requests\Admin\UpdateCapexProjectRequest;
use App\Http\Requests\Admin\UpdateCapexProjectStatusRequest;
use App\Models\CapexProject;
use App\Models\Department;
use App\Models\User;
use App\Services\CapExAccountingService;
use App\Services\CapexLaborExportService;
use App\Services\CapexProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CapexProjectController extends Controller
{
    public function __construct(
        public CapexProjectService $capexProjectService,
        public CapExAccountingService $capexAccountingService,
        public CapexLaborExportService $capexLaborExportService,
    ) {}

    /**
     * Display a listing of CapEx projects (Portfolio & Master Data hub).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $departmentsQuery = Department::query()->where('is_active', true)->orderBy('name');
        if ($user->isManager() && $user->department_id) {
            $departmentsQuery->where('id', $user->department_id);
        }
        $departments = $departmentsQuery->get(['id', 'code', 'name']);

        $activeTab = $request->query('tab', 'portfolio');

        $filters = [
            'status' => $request->query('status', 'ALL'),
            'department_id' => $request->query('department_id'),
            'search' => $request->query('search', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
            'sort_by' => $request->query('sort_by', 'created_at'),
            'sort_dir' => $request->query('sort_dir', 'desc'),
        ];

        $attributionFilters = [
            'project_id' => $request->query('project_id'),
            'department_id' => $request->query('department_id'),
            'search' => $request->query('search', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];

        $data = $this->capexProjectService->list($filters, $user);

        // CapEx project list for dropdown filters
        $projectsListQuery = CapexProject::query()->orderBy('project_code');
        if ($user->isManager() && $user->department_id) {
            $projectsListQuery->where('department_id', $user->department_id);
        }
        $capexProjectsList = $projectsListQuery->get(['id', 'project_code', 'name', 'asset_code', 'department_id']);

        $attributionData = null;
        if ($activeTab === 'attribution') {
            $attributionData = $this->capexAccountingService->getLaborAttributionReport($attributionFilters, $user);
        }

        return Inertia::render('admin/CapexProjects/Index', [
            'projects' => $data['projects'],
            'stats' => $data['stats'],
            'departments' => $departments,
            'filters' => $filters,
            'activeTab' => $activeTab,
            'attribution' => $attributionData,
            'attributionFilters' => $attributionFilters,
            'capexProjectsList' => $capexProjectsList,
        ]);
    }

    /**
     * Store a newly created CapEx project.
     */
    public function store(StoreCapexProjectRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->capexProjectService->create($request->validated(), $user);

        return redirect()
            ->route('admin.capex-projects.index', ['tab' => 'portfolio'])
            ->with('success', __('Proyek CapEx berhasil didaftarkan.'));
    }

    /**
     * Display the specified CapEx project (Labor cockpit & detail view).
     */
    public function show(Request $request, CapexProject $capexProject): Response
    {
        /** @var User $user */
        $user = $request->user();

        $detail = $this->capexProjectService->getProjectDetail($capexProject, $user);

        return Inertia::render('admin/CapexProjects/Show', [
            'project' => $detail['project'],
            'metrics' => $detail['metrics'],
        ]);
    }

    /**
     * Update the specified CapEx project record.
     */
    public function update(UpdateCapexProjectRequest $request, CapexProject $capexProject): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->capexProjectService->update($capexProject, $request->validated(), $user);

        return redirect()
            ->back()
            ->with('success', __('Data proyek CapEx berhasil diperbarui.'));
    }

    /**
     * Update the lifecycle status of the specified CapEx project.
     */
    public function updateStatus(UpdateCapexProjectStatusRequest $request, CapexProject $capexProject): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->capexProjectService->updateStatus(
            $capexProject,
            $request->validated('status'),
            $request->validated('notes'),
            $user
        );

        return redirect()
            ->back()
            ->with('success', __('Status proyek CapEx berhasil diperbarui.'));
    }

    /**
     * Update the physical progress percentage of the specified CapEx project.
     */
    public function updateProgress(UpdateCapexProjectProgressRequest $request, CapexProject $capexProject): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->capexProjectService->updateProgress(
            $capexProject,
            (float) $request->validated('physical_progress_pct'),
            $user
        );

        return redirect()
            ->back()
            ->with('success', __('Kemajuan fisik proyek berhasil diperbarui.'));
    }

    /**
     * Remove the specified CapEx project from storage.
     */
    public function destroy(Request $request, CapexProject $capexProject): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->capexProjectService->delete($capexProject, $user);

        return redirect()
            ->route('admin.capex-projects.index', ['tab' => 'portfolio'])
            ->with('success', __('Proyek CapEx berhasil dihapus.'));
    }

    /**
     * Export CapEx labor attribution schedule to Excel (.xlsx) or CSV (E07-04).
     */
    public function exportAttribution(Request $request): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Department authority check: Managers cannot export foreign department records
        if ($user->isManager() && $request->filled('department_id')) {
            $reqDeptId = (int) $request->input('department_id');
            if ($user->department_id !== null && $reqDeptId !== (int) $user->department_id) {
                abort(403, __('Anda tidak memiliki akses untuk mengekspor data departemen lain.'));
            }
        }

        $filters = [
            'project_id' => $request->input('project_id'),
            'capex_project_id' => $request->input('capex_project_id'),
            'department_id' => $request->input('department_id'),
            'search' => $request->input('search'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $format = strtolower((string) $request->input('format', 'xlsx'));

        return $this->capexLaborExportService->export($filters, $user, $format);
    }
}
