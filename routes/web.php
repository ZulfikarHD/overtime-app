<?php

use App\Http\Controllers\Admin\AdministrationController;
use App\Http\Controllers\Admin\CapexProjectController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeImportController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\OperationalCalendarController;
use App\Http\Controllers\Admin\PolicyThresholdController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Budgets\OvertimeBudgetController;
use App\Http\Controllers\Budgets\OvertimeBudgetImportController;
use App\Http\Controllers\DashboardBurnIndexController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeSelfServiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Overtime\OvertimeApprovalController;
use App\Http\Controllers\Overtime\OvertimeItemAuditController;
use App\Http\Controllers\Overtime\OvertimeSubmissionController;
use App\Http\Controllers\Overtime\SpklDocumentController;
use App\Http\Controllers\Reports\EmployeeReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function (Request $request) {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('auth/Login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => $request->session()->get('status'),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/kpi-cards', [DashboardController::class, 'kpiCards'])->name('dashboard.kpi-cards');
    Route::get('dashboard/charts/daily-burn', [DashboardController::class, 'dailyBurnChart'])->name('dashboard.charts.daily-burn');
    Route::get('dashboard/charts/section-burn', [DashboardController::class, 'sectionBurnComparison'])->name('dashboard.charts.section-burn');
    Route::get('dashboard/charts/leaderboard', [DashboardController::class, 'leaderboard'])->name('dashboard.charts.leaderboard');
    Route::get('dashboard/charts/category', [DashboardController::class, 'categoryDistribution'])->name('dashboard.charts.category');
    Route::get('dashboard/charts/trend-working', [DashboardController::class, 'trendWorkingTime'])->name('dashboard.charts.trend-working');
    Route::get('dashboard/charts/daily-index', [DashboardController::class, 'dailyIndexTrend'])->name('dashboard.charts.daily-index');
    Route::get('dashboard/charts/day-type', [DashboardController::class, 'dayTypeBreakdown'])->name('dashboard.charts.day-type');
    Route::get('dashboard/employee-summary', [DashboardController::class, 'employeeSummaryTable'])->name('dashboard.employee-summary');
    Route::get('my/dashboard', [EmployeeSelfServiceController::class, 'index'])->name('my.dashboard');

    // Dashboard Burn Index & Budget Analytics (E05 - Admin, Manager, Team Leader)
    Route::middleware(['role:admin,manager,team_leader'])->prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/burn-index', [DashboardBurnIndexController::class, 'index'])->name('burn-index');
        Route::post('/burn-index/recalculate', [DashboardBurnIndexController::class, 'recalculate'])->name('burn-index.recalculate');
        Route::get('/burn-index/export-pdf', [DashboardBurnIndexController::class, 'exportPdf'])->name('burn-index.export-pdf');
        Route::get('/burn-index/{section}', [DashboardBurnIndexController::class, 'show'])->name('burn-index.show');
    });

    // Legacy/Scrum alias redirect for CapEx vs OpEx tab (E05-03 per UX Plan Section 1.3 & 5.1)
    Route::redirect('/reports/capex-opex', '/dashboard/burn-index?tab=capex-opex');

    // Legacy/Scrum alias redirects for CapEx Project Portfolio & Labor Attribution (E07 per UX Plan Section 1.3 & 5.1/5.2)
    Route::get('/reports/capex-projects/portfolio', function (Request $request) {
        $query = $request->query();
        $query['tab'] = 'portfolio';

        return redirect()->route('admin.capex-projects.index', $query);
    });
    Route::get('/reports/capex-labor', function (Request $request) {
        $query = $request->query();
        $query['tab'] = 'attribution';

        return redirect()->route('admin.capex-projects.index', $query);
    });
    Route::get('/reports/capex-labor/export', [CapexProjectController::class, 'exportAttribution'])
        ->middleware(['role:admin,manager'])
        ->name('reports.capex-labor.export');

    // Individual Employee Reporting & Welfare Tracking (E06 - Admin, Manager, Team Leader, User)
    Route::middleware(['role:admin,manager,team_leader,user'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('/employees', [EmployeeReportController::class, 'index'])->name('employees.index');
        Route::get('/employees/search', [EmployeeReportController::class, 'search'])->name('employees.search');
        Route::get('/employees/{npk}', [EmployeeReportController::class, 'show'])->name('employees.show');
        Route::get('/employees/{npk}/timesheet', [EmployeeReportController::class, 'timesheet'])->name('employees.timesheet');
        Route::get('/employees/{npk}/timesheet/export', [EmployeeReportController::class, 'exportTimesheet'])->name('employees.timesheet.export');
    });

    // Strategic Analytics & Decision Intelligence Hub (E09-06 - Admin & Manager)
    Route::middleware(['role:admin,manager'])->prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/predictive', [AnalyticsController::class, 'predictive'])->name('predictive');
        Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
    });

    // Role-protected routes for authorization verification and testing
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'admin-access-granted']))->name('overview');

        // Master Data Hub (E02-01, E02-02, E02-03)
        Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data');
        Route::redirect('/departments', '/admin/master-data?tab=departments');
        Route::redirect('/sections', '/admin/master-data?tab=departments');
        Route::redirect('/employees', '/admin/master-data?tab=employees');
        Route::redirect('/calendar', '/admin/master-data?tab=calendar');

        // Department & Section Hierarchy CRUD (E02-01)
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        // Section Hierarchy CRUD (E02-01)
        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

        // Employee Roster Management & CSV Import (E02-02)
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        Route::post('/employees/import/preview', [EmployeeImportController::class, 'preview'])->name('employees.import.preview');
        Route::post('/employees/import', [EmployeeImportController::class, 'import'])->name('employees.import');
        Route::get('/employees/template', [EmployeeImportController::class, 'template'])->name('employees.template');

        // Operational Calendar Management & Holiday Import (E02-03)
        Route::put('/calendar/{date}', [OperationalCalendarController::class, 'update'])->name('calendar.update');
        Route::post('/calendar/import-holidays/preview', [OperationalCalendarController::class, 'preview'])->name('calendar.import.preview');
        Route::post('/calendar/import-holidays', [OperationalCalendarController::class, 'import'])->name('calendar.import');
        Route::get('/calendar/template', [OperationalCalendarController::class, 'template'])->name('calendar.template');

        // Administration Hub (E02-04, E02-05)
        Route::get('/administration', [AdministrationController::class, 'index'])->name('administration');
        Route::redirect('/policies', '/admin/administration?tab=policies');
        Route::redirect('/policy-thresholds', '/admin/administration?tab=policies');
        Route::redirect('/users', '/admin/administration?tab=users');

        // Policy Threshold Configuration (E02-04)
        Route::post('/policy-thresholds', [PolicyThresholdController::class, 'store'])->name('policy-thresholds.store');
        Route::put('/policy-thresholds/{policy_threshold}', [PolicyThresholdController::class, 'update'])->name('policy-thresholds.update');
        Route::delete('/policy-thresholds/{policy_threshold}', [PolicyThresholdController::class, 'destroy'])->name('policy-thresholds.destroy');

        // User Account Management & RBAC (E02-05)
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/reset-password', [UserController::class, 'sendResetLink'])->name('users.reset-password');
    });

    // CapEx Project Master Data & Portfolio Hub (E07-01 - Admin & Manager)
    Route::middleware(['role:admin,manager'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/capex-projects/export-attribution', [CapexProjectController::class, 'exportAttribution'])->name('capex-projects.export-attribution');
        Route::get('/capex-projects', [CapexProjectController::class, 'index'])->name('capex-projects.index');
        Route::post('/capex-projects', [CapexProjectController::class, 'store'])->name('capex-projects.store');
        Route::get('/capex-projects/{capex_project}', [CapexProjectController::class, 'show'])->name('capex-projects.show');
        Route::put('/capex-projects/{capex_project}', [CapexProjectController::class, 'update'])->name('capex-projects.update');
        Route::delete('/capex-projects/{capex_project}', [CapexProjectController::class, 'destroy'])->name('capex-projects.destroy');
        Route::patch('/capex-projects/{capex_project}/status', [CapexProjectController::class, 'updateStatus'])->name('capex-projects.status.update');
        Route::patch('/capex-projects/{capex_project}/progress', [CapexProjectController::class, 'updateProgress'])->name('capex-projects.progress.update');
    });

    // Calendar Classification API for timesheet and general auto-classification (E02-03)
    Route::get('/api/calendar/{date}', [CalendarController::class, 'show'])->name('api.calendar.show');

    // Overtime Budget Planning Hub (E02-07 - Admin & Manager)
    Route::middleware(['role:admin,manager'])->prefix('budgets')->name('budgets.')->group(function () {
        Route::get('/planning', [OvertimeBudgetController::class, 'index'])->name('planning');
        Route::post('/planning', [OvertimeBudgetController::class, 'store'])->name('store');
        Route::post('/planning/import/preview', [OvertimeBudgetImportController::class, 'preview'])->name('import.preview');
        Route::post('/planning/import', [OvertimeBudgetImportController::class, 'import'])->name('import');
        Route::get('/planning/template', [OvertimeBudgetImportController::class, 'template'])->name('template');
    });

    // Overtime Submissions (E03 - Daily Overtime & Timesheets)
    Route::middleware(['role:admin,manager,team_leader'])->prefix('overtime')->name('overtime.')->group(function () {
        Route::get('/submissions', [OvertimeSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/create', [OvertimeSubmissionController::class, 'create'])->name('submissions.create');
        Route::post('/submissions', [OvertimeSubmissionController::class, 'store'])->name('submissions.store');
        Route::get('/submissions/roster/{section}', [OvertimeSubmissionController::class, 'roster'])->name('submissions.roster');
        Route::get('/policy-check', [OvertimeSubmissionController::class, 'policyCheck'])->name('policy-check');
        Route::get('/submissions/{submission}', [OvertimeSubmissionController::class, 'show'])->name('submissions.show');
        Route::get('/submissions/{submission}/edit', [OvertimeSubmissionController::class, 'edit'])->name('submissions.edit');
        Route::put('/submissions/{submission}', [OvertimeSubmissionController::class, 'update'])->name('submissions.update');
        Route::delete('/submissions/{submission}', [OvertimeSubmissionController::class, 'destroy'])->name('submissions.destroy');
        Route::post('/submissions/{submission}/spkl', [SpklDocumentController::class, 'attach'])->name('submissions.spkl.attach');
        Route::patch('/submissions/{submission}/spkl/verify', [SpklDocumentController::class, 'verify'])->name('submissions.spkl.verify');
        Route::get('/submissions/{submission}/spkl/download', [SpklDocumentController::class, 'download'])->name('submissions.spkl.download');
    });

    // Overtime Approvals Queue & Decisions (E04-01, E04-02 & E04-03 — Manager & Admin only)
    Route::middleware(['role:admin,manager'])->prefix('overtime')->name('overtime.')->group(function () {
        Route::get('/approvals', [OvertimeApprovalController::class, 'index'])->name('approvals');
        Route::get('/approvals/export', [OvertimeApprovalController::class, 'export'])->name('approvals.export');
        Route::post('/approvals/bulk', [OvertimeApprovalController::class, 'bulkProcess'])->name('approvals.bulk');
        Route::post('/submissions/{submission}/approve-items', [OvertimeApprovalController::class, 'approveItems'])->name('submissions.approve-items');
        Route::get('/items/{item}/audit', [OvertimeItemAuditController::class, 'index'])->name('items.audit');
    });

    // Admin-only Overtime Overrides (E04-06)
    Route::middleware(['role:admin'])->prefix('overtime')->name('overtime.')->group(function () {
        Route::patch('/submissions/{submission}/unlock', [OvertimeSubmissionController::class, 'forceUnlock'])->name('submissions.unlock');
    });

    Route::middleware(['role:admin,manager'])->prefix('manager')->name('manager.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'manager-access-granted']))->name('overview');
    });

    Route::middleware(['role:admin,team_leader'])->prefix('team-leader')->name('team-leader.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'team-leader-access-granted']))->name('overview');
    });

    // In-App Notifications (E03-05)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::patch('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
    });
});

require __DIR__.'/settings.php';
