<?php

namespace App\Http\Controllers\Overtime;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeApprovalController extends Controller
{
    /**
     * Display the pending approval queue for Managers and Admins (E04-01).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $todayWib = Carbon::now('Asia/Jakarta')->toDateString();
        $defaultDateFrom = Carbon::now('Asia/Jakarta')->subDays(6)->toDateString();

        $dateFrom = $request->filled('date_from')
            ? (string) $request->input('date_from')
            : $defaultDateFrom;
        $dateTo = $request->filled('date_to')
            ? (string) $request->input('date_to')
            : $todayWib;

        $sort = (string) $request->input('sort', 'date');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = OvertimeSubmission::query()
            ->with([
                'section:id,name,code,department_id',
                'department:id,name,code',
                'submittedBy:id,name,npk',
                'spklDocument',
                'items' => function ($itemsQuery) {
                    $itemsQuery->with([
                        'employee:id,npk,full_name,job_position,hourly_rate',
                        'capexProject:id,project_code,name',
                        'anomalyLogs' => fn ($q) => $q->where('is_dismissed', false),
                    ]);
                },
            ])
            ->withCount('items')
            ->withSum('items as total_cost_cached', 'total_cost_snapshot')
            ->withCount([
                'items as anomaly_count' => function (Builder $q) {
                    $q->whereHas('anomalyLogs', function (Builder $aq) {
                        $aq->where('is_dismissed', false);
                    });
                },
            ]);

        // Role scoping: Manager → own department; Admin → plant-wide
        if ($user->isManager() && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        // Admin department filter
        if ($user->isAdmin() && $request->filled('department_id')) {
            $filterDeptId = (int) $request->input('department_id');
            $query->where('department_id', $filterDeptId);
        }

        // Section filter (authorized only)
        if ($request->filled('section_id')) {
            $filterSecId = (int) $request->input('section_id');
            if ($user->canAccessSection($filterSecId)) {
                $query->where('section_id', $filterSecId);
            }
        }

        // Status filter — default to Menunggu Review (SUBMITTED) per UX plan
        $statusInput = $request->input('status');
        if ($statusInput === null || $statusInput === '' || $statusInput === 'SUBMITTED') {
            $query->where('status', 'SUBMITTED');
        } elseif ($statusInput === 'PENDING') {
            $query->whereIn('status', ['SUBMITTED', 'PARTIALLY_APPROVED']);
        } elseif ($statusInput === 'ALL') {
            // No status constraint — show all statuses in date range
        } elseif (is_array($statusInput)) {
            $query->whereIn('status', $statusInput);
        } elseif (str_contains((string) $statusInput, ',')) {
            $query->whereIn('status', explode(',', (string) $statusInput));
        } else {
            $query->where('status', $statusInput);
        }

        // SPKL status filter (non-blocking indicator)
        if ($request->filled('spkl_status')) {
            $spklStatus = (string) $request->input('spkl_status');
            if ($spklStatus === 'OVERDUE') {
                $query->whereHas('spklDocument', function (Builder $q) use ($todayWib) {
                    $q->where('status', 'PENDING')
                        ->whereDate('due_date', '<', $todayWib);
                });
            } elseif ($spklStatus === 'NONE') {
                $query->whereDoesntHave('spklDocument');
            } elseif (in_array($spklStatus, ['PENDING', 'ATTACHED', 'VERIFIED'], true)) {
                $query->whereHas('spklDocument', function (Builder $q) use ($spklStatus) {
                    $q->where('status', $spklStatus);
                });
            }
        }

        // Date range (defaults to last 7 days inclusive)
        $query->whereDate('operational_date', '>=', $dateFrom)
            ->whereDate('operational_date', '<=', $dateTo);

        // Sorting
        match ($sort) {
            'section' => $query->orderBy(
                Section::select('name')
                    ->whereColumn('sections.id', 'overtime_submissions.section_id')
                    ->limit(1),
                $direction,
            )->orderByDesc('id'),
            'total_hours' => $query->orderBy('total_hours_cached', $direction)->orderByDesc('id'),
            default => $query->orderBy('operational_date', $direction)->orderByDesc('id'),
        };

        $submissions = $query->paginate(20)->withQueryString();

        // Pending summary (scoped, independent of current status filter)
        $pendingBase = OvertimeSubmission::query()
            ->whereIn('status', ['SUBMITTED', 'PARTIALLY_APPROVED']);

        if ($user->isManager() && $user->department_id) {
            $pendingBase->where('department_id', $user->department_id);
        }

        $pendingCount = (clone $pendingBase)->count();
        $pendingHours = (float) (clone $pendingBase)->sum('total_hours_cached');

        // Filter option lists
        $availableSectionsQuery = Section::query()->where('is_active', true)->orderBy('name');
        $availableDepartments = collect();

        if ($user->isAdmin()) {
            $availableDepartments = Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']);

            if ($request->filled('department_id')) {
                $availableSectionsQuery->where('department_id', (int) $request->input('department_id'));
            }
        } elseif ($user->isManager() && $user->department_id) {
            $availableSectionsQuery->where('department_id', $user->department_id);
        }

        $availableSections = $availableSectionsQuery->get(['id', 'department_id', 'code', 'name']);

        return Inertia::render('overtime/ApprovalQueue', [
            'submissions' => $submissions,
            'available_sections' => $availableSections,
            'available_departments' => $availableDepartments,
            'pending_count' => $pendingCount,
            'pending_hours' => $pendingHours,
            'filters' => [
                'status' => $request->input('status', 'SUBMITTED'),
                'department_id' => $request->input('department_id', ''),
                'section_id' => $request->input('section_id', ''),
                'spkl_status' => $request->input('spkl_status', ''),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }
}
