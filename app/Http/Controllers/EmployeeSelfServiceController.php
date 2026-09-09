<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeSelfServiceController extends Controller
{
    public function __construct(
        public EmployeeReportService $employeeReportService,
    ) {}

    /**
     * Display the personal self-service dashboard for line operators.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $employee = null;
        if ($user->npk) {
            $employee = Employee::query()
                ->with(['department:id,name,code', 'section:id,name,code'])
                ->where('npk', $user->npk)
                ->first();
        }

        $now = Carbon::now('Asia/Jakarta');
        $fiscalYear = (int) $now->format('Y');
        $fiscalMonth = (int) $now->format('n');

        $summary = null;
        $welfareStatus = null;
        $recentTimesheet = null;

        if ($employee) {
            $summary = $this->employeeReportService->getSummary($employee->id, $fiscalYear, $fiscalMonth);
            $welfareStatus = $this->employeeReportService->getWelfareStatus($employee->id, $fiscalYear, $fiscalMonth);
            $recentTimesheet = $this->employeeReportService->getTimesheet($employee->id, ['all_time' => true], 5);
        }

        return Inertia::render('dashboard/EmployeeSelfService', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'npk' => $employee->npk,
                'full_name' => $employee->full_name,
                'job_position' => $employee->job_position,
                'department' => $employee->department ? [
                    'id' => $employee->department->id,
                    'name' => $employee->department->name,
                    'code' => $employee->department->code,
                ] : null,
                'section' => $employee->section ? [
                    'id' => $employee->section->id,
                    'name' => $employee->section->name,
                    'code' => $employee->section->code,
                ] : null,
            ] : null,
            'summary' => $summary,
            'welfare_status' => $welfareStatus,
            'recent_timesheet' => $recentTimesheet ? $recentTimesheet['data'] : [],
            'fiscal_year' => $fiscalYear,
            'fiscal_month' => $fiscalMonth,
        ]);
    }
}
