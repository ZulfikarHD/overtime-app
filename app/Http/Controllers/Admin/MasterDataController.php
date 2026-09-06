<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Services\OperationalCalendarService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    public function __construct(
        public OperationalCalendarService $calendarService,
    ) {}

    /**
     * Display the Master Data Hub.
     */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'departments');

        $departments = Department::query()
            ->withCount([
                'employees',
                'sections',
                'overtimeSubmissions',
            ])
            ->with([
                'sections' => function ($query) {
                    $query->withCount([
                        'employees',
                        'overtimeSubmissions',
                    ])->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        $search = $request->query('search');
        $deptFilter = $request->query('department_id');
        $status = $request->query('status', 'all');

        $employeesQuery = Employee::query()
            ->with([
                'department:id,code,name,default_hourly_rate',
                'section:id,code,name,department_id',
            ])
            ->withCount('overtimeItems');

        if ($search) {
            $term = '%'.trim((string) $search).'%';
            $employeesQuery->where(function ($q) use ($term) {
                $q->where('npk', 'like', $term)
                    ->orWhere('full_name', 'like', $term)
                    ->orWhere('job_position', 'like', $term)
                    ->orWhereHas('section', function ($sq) use ($term) {
                        $sq->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term);
                    })
                    ->orWhereHas('department', function ($dq) use ($term) {
                        $dq->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term);
                    });
            });
        }

        if ($deptFilter && is_numeric($deptFilter)) {
            $employeesQuery->where('department_id', (int) $deptFilter);
        }

        if ($status === 'active') {
            $employeesQuery->where('is_active', true);
        } elseif ($status === 'inactive') {
            $employeesQuery->where('is_active', false);
        }

        $employees = $employeesQuery
            ->orderBy('npk')
            ->paginate(25)
            ->withQueryString();

        $employeeStats = [
            'total' => Employee::count(),
            'active' => Employee::where('is_active', true)->count(),
            'inactive' => Employee::where('is_active', false)->count(),
        ];

        // Operational Calendar Data
        $currentYear = (int) $request->query('year', now()->year);
        $currentMonth = (int) $request->query('month', now()->month);
        if ($currentMonth < 1 || $currentMonth > 12) {
            $currentMonth = (int) now()->month;
        }
        if ($currentYear < 2020 || $currentYear > 2050) {
            $currentYear = (int) now()->year;
        }

        $calendarDays = $this->calendarService->getMonthCalendar($currentYear, $currentMonth)->map(fn ($day) => [
            'calendar_date' => $day->calendar_date->format('Y-m-d'),
            'day_type' => $day->day_type,
            'is_holiday' => (bool) $day->is_holiday,
            'holiday_name' => $day->holiday_name,
            'description' => $day->description,
            'day_of_week' => (int) $day->calendar_date->dayOfWeekIso, // 1 (Monday) to 7 (Sunday)
            'day_number' => (int) $day->calendar_date->day,
        ]);

        $calendarStats = $this->calendarService->getMonthStats($currentYear, $currentMonth);

        return Inertia::render('admin/MasterData', [
            'activeTab' => $tab,
            'departments' => $departments,
            'employees' => $employees,
            'employeeStats' => $employeeStats,
            'filters' => [
                'search' => $search ?? '',
                'department_id' => $deptFilter ? (int) $deptFilter : null,
                'status' => $status ?? 'all',
            ],
            'calendar' => [
                'year' => $currentYear,
                'month' => $currentMonth,
                'days' => $calendarDays,
                'stats' => $calendarStats,
            ],
        ]);
    }
}
