<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
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
        ]);
    }
}
