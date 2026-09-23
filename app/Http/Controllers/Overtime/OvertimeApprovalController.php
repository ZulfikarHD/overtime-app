<?php

namespace App\Http\Controllers\Overtime;

use App\Actions\Overtime\ApproveOvertimeItemsAction;
use App\Actions\Overtime\ApproveSplEntriesAction;
use App\Actions\Overtime\BulkApproveSplEntriesAction;
use App\Actions\Overtime\BulkApproveSubmissionsAction;
use App\Exceptions\OptimisticLockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Overtime\ApproveOvertimeItemsRequest;
use App\Http\Requests\Overtime\BulkApprovalRequest;
use App\Models\Department;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use App\Services\OvertimeExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OvertimeApprovalController extends Controller
{
    public function __construct(
        public ApproveSplEntriesAction $approveSplEntriesAction,
        public BulkApproveSplEntriesAction $bulkApproveSplEntriesAction,
        // Kept for legacy approveItems endpoint
        public ApproveOvertimeItemsAction $approveOvertimeItemsAction,
        public BulkApproveSubmissionsAction $bulkApproveSubmissionsAction,
        public OvertimeExportService $exportService,
    ) {}

    /**
     * Display the SPL-based approval queue (new flow).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();
        $defaultDateFrom = Carbon::now('Asia/Jakarta')->startOfMonth()->toDateString();

        $dateFrom = $request->filled('date_from') ? (string) $request->input('date_from') : $defaultDateFrom;
        $dateTo = $request->filled('date_to') ? (string) $request->input('date_to') : $todayWib;
        $sort = (string) $request->input('sort', 'date');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $statusFilter = (string) $request->input('status', 'PENDING');

        // --- Build grouped query ---
        $query = DB::table('spl_entries')
            ->select([
                'section_id',
                DB::raw('DATE(realization_date) as date'),
                DB::raw('MAX(day_type) as day_type'),
                DB::raw('COUNT(*) as entries_count'),
                DB::raw('ROUND(SUM(total_hours), 2) as total_hours'),
                DB::raw("SUM(CASE WHEN status = 'PENDING'   THEN 1 ELSE 0 END) as pending_count"),
                DB::raw("SUM(CASE WHEN status = 'APPROVED'  THEN 1 ELSE 0 END) as approved_count"),
                DB::raw("SUM(CASE WHEN status = 'REJECTED'  THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw('MAX(imported_by_user_id) as imported_by_user_id'),
            ])
            ->whereNotNull('section_id')
            ->whereDate('realization_date', '>=', $dateFrom)
            ->whereDate('realization_date', '<=', $dateTo)
            ->groupBy('section_id', DB::raw('DATE(realization_date)'));

        // Manager → own department only
        if ($user->isManager() && $user->department_id) {
            $sectionIds = Section::where('department_id', $user->department_id)->pluck('id');
            $query->whereIn('section_id', $sectionIds);
        }

        if ($request->filled('department_id')) {
            $deptId = (int) $request->input('department_id');
            $sectionIds = Section::where('department_id', $deptId)->pluck('id');
            $query->whereIn('section_id', $sectionIds);
        }

        if ($request->filled('section_id')) {
            $query->where('section_id', (int) $request->input('section_id'));
        }

        // Status filter
        $query->havingRaw(match ($statusFilter) {
            'PENDING' => "SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) = 0",
            'APPROVED' => "SUM(CASE WHEN status != 'APPROVED' THEN 1 ELSE 0 END) = 0",
            'REJECTED' => "SUM(CASE WHEN status != 'REJECTED' THEN 1 ELSE 0 END) = 0",
            'PARTIALLY_APPROVED' => "SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) > 0",
            default => '1=1', // ALL
        });

        // Sorting
        $query->orderBy(
            $sort === 'section' ? 'section_id' : 'date',
            $direction
        );

        $paginated = $query->paginate(20)->withQueryString();

        // Enrich with section + department names
        $sectionIds = collect($paginated->items())->pluck('section_id')->unique()->filter()->values();
        $sections = Section::with('department:id,name,code')
            ->whereIn('id', $sectionIds)
            ->get(['id', 'name', 'code', 'department_id'])
            ->keyBy('id');

        $groups = collect($paginated->items())->map(function (object $row) use ($sections): array {
            $section = $sections->get($row->section_id);
            $total = $row->entries_count;
            $approved = (int) $row->approved_count;
            $rejected = (int) $row->rejected_count;
            $pending = (int) $row->pending_count;

            $status = match (true) {
                $pending === $total => 'PENDING',
                $approved === $total => 'APPROVED',
                $rejected === $total => 'REJECTED',
                default => 'PARTIALLY_APPROVED',
            };

            return [
                'section_id' => $row->section_id,
                'date' => $row->date,
                'day_type' => $row->day_type,
                'entries_count' => $total,
                'total_hours' => (float) $row->total_hours,
                'pending_count' => $pending,
                'approved_count' => $approved,
                'rejected_count' => $rejected,
                'status' => $status,
                'section' => $section ? [
                    'id' => $section->id,
                    'name' => $section->name,
                    'code' => $section->code,
                ] : null,
                'department' => $section?->department ? [
                    'id' => $section->department->id,
                    'name' => $section->department->name,
                    'code' => $section->department->code,
                ] : null,
            ];
        })->values()->all();

        // Replace the raw items with enriched groups while keeping pagination meta
        $paginationMeta = [
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'links' => $paginated->linkCollection()->toArray(),
            'path' => $paginated->path(),
            'next_page_url' => $paginated->nextPageUrl(),
            'prev_page_url' => $paginated->previousPageUrl(),
        ];

        // Pending summary (for badge)
        $pendingBase = DB::table('spl_entries')
            ->select('section_id', DB::raw('DATE(realization_date) as date'))
            ->where('status', 'PENDING')
            ->whereNotNull('section_id')
            ->groupBy('section_id', DB::raw('DATE(realization_date)'));

        if ($user->isManager() && $user->department_id) {
            $pendingBase->whereIn('section_id', Section::where('department_id', $user->department_id)->pluck('id'));
        }

        $pendingGroupCount = $pendingBase->get()->count();
        $pendingHours = (float) DB::table('spl_entries')
            ->where('status', 'PENDING')
            ->whereNotNull('section_id')
            ->when($user->isManager() && $user->department_id, fn ($q) => $q->whereIn('section_id',
                Section::where('department_id', $user->department_id)->pluck('id')
            ))
            ->sum('total_hours');

        $availableSections = collect();
        $availableDepartments = collect();

        $sectionsQuery = Section::query()->where('is_active', true)->orderBy('name');
        if ($user->isAdmin()) {
            $availableDepartments = Department::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
            if ($request->filled('department_id')) {
                $sectionsQuery->where('department_id', (int) $request->input('department_id'));
            }
        } elseif ($user->isManager() && $user->department_id) {
            $sectionsQuery->where('department_id', $user->department_id);
        }
        $availableSections = $sectionsQuery->get(['id', 'department_id', 'code', 'name']);

        return Inertia::render('overtime/ApprovalQueue', [
            'groups' => $groups,
            'pagination' => $paginationMeta,
            'available_sections' => $availableSections,
            'available_departments' => $availableDepartments,
            'pending_count' => $pendingGroupCount,
            'pending_hours' => $pendingHours,
            'filters' => [
                'status' => $statusFilter,
                'department_id' => $request->input('department_id', ''),
                'section_id' => $request->input('section_id', ''),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Return JSON of individual SplEntry rows for a (section_id, date) group.
     * Used by the frontend to populate the expand accordion.
     */
    public function splGroupEntries(Request $request): JsonResponse
    {
        $sectionId = (int) $request->input('section_id');
        $date = (string) $request->input('date');

        /** @var User $user */
        $user = $request->user();

        if ($user->isManager() && $user->department_id !== null) {
            $section = Section::find($sectionId);
            if ($section && $section->department_id !== $user->department_id) {
                abort(403);
            }
        }

        $entries = SplEntry::where('section_id', $sectionId)
            ->whereDate('realization_date', $date)
            ->orderBy('employee_name_snapshot')
            ->get([
                'id', 'npk_snapshot', 'employee_name_snapshot',
                'start_time', 'end_time', 'total_hours',
                'jenis_pekerjaan', 'type_ot_code', 'keterangan_lembur',
                'day_type', 'status', 'lock_version',
                'reviewed_at', 'rejection_reason',
            ]);

        return response()->json($entries);
    }

    /**
     * Approve or reject all entries in one (section_id, date) group.
     */
    public function approveSplGroup(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'date' => ['required', 'date'],
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.entry_id' => ['required', 'integer', 'exists:spl_entries,id'],
            'decisions.*.action' => ['required', 'string', 'in:APPROVED,REJECTED'],
            'decisions.*.lock_version' => ['nullable', 'integer'],
            'decisions.*.rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Manager department guard
        if ($user->isManager() && $user->department_id !== null) {
            $section = Section::find($validated['section_id']);
            if ($section && $section->department_id !== $user->department_id) {
                abort(403, __('Anda tidak memiliki akses persetujuan untuk seksi ini.'));
            }
        }

        try {
            /** @var array<int, array{entry_id: int, action: string, rejection_reason?: string|null, lock_version?: int|null}> $decisions */
            $decisions = $validated['decisions'];
            $result = $this->approveSplEntriesAction->execute($decisions, $user);
        } catch (OptimisticLockException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage(), 'conflict' => true], 409)
                : back()->withErrors(['conflict' => $e->getMessage()]);
        }

        $msg = __(':approved disetujui, :rejected ditolak.', $result);

        if ($request->wantsJson()) {
            return response()->json(['message' => $msg, 'result' => $result]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $msg]);

        return redirect()->route('overtime.approvals');
    }

    /**
     * Bulk approve/reject multiple (section_id, date) groups.
     */
    public function bulkProcess(BulkApprovalRequest $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<array{section_id: int, date: string}> $groups */
        $groups = $request->validated('groups');
        $action = (string) $request->validated('action');
        $rejectionReason = $request->filled('rejection_reason') ? (string) $request->validated('rejection_reason') : null;

        $result = $this->bulkApproveSplEntriesAction->execute($groups, $action, $rejectionReason, $user);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        Inertia::flash('toast', [
            'type' => $result['skipped'] > 0 ? 'warning' : 'success',
            'message' => $result['message'],
        ]);

        return redirect()->route('overtime.approvals');
    }

    /**
     * Export filtered overtime records (kept as-is, reads old overtime_submissions).
     */
    public function export(Request $request): SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isManager() && $request->filled('department_id')) {
            $reqDeptId = (int) $request->input('department_id');
            if ($user->department_id !== null && $reqDeptId !== (int) $user->department_id) {
                abort(403, __('Anda tidak memiliki akses untuk mengekspor data departemen lain.'));
            }
        }

        $format = strtolower((string) $request->input('format', 'csv'));

        return $this->exportService->export($request->all(), $user, $format);
    }

    /**
     * Legacy: item-level approval for old OvertimeSubmission records.
     */
    public function approveItems(ApproveOvertimeItemsRequest $request, OvertimeSubmission $submission): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isManager() && $user->department_id !== null && $submission->department_id !== $user->department_id) {
            abort(403, __('Anda tidak memiliki akses persetujuan untuk departemen pengajuan ini.'));
        }

        /** @var array<int, array{item_id: int, action: string, rejection_reason?: string|null, lock_version?: int|null}> $decisions */
        $decisions = $request->validated('decisions');

        try {
            $this->approveOvertimeItemsAction->execute($submission->id, $decisions, $user->id);
        } catch (OptimisticLockException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage(), 'conflict' => true], 409)
                : back()->withErrors(['conflict' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['message' => __('Persetujuan disimpan.')])
            : redirect()->route('overtime.approvals');
    }
}
