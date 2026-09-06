<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeImportController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\OperationalCalendarController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Api\CalendarController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

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
    });

    // Calendar Classification API for timesheet and general auto-classification (E02-03)
    Route::get('/api/calendar/{date}', [CalendarController::class, 'show'])->name('api.calendar.show');

    Route::middleware(['role:admin,manager'])->prefix('manager')->name('manager.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'manager-access-granted']))->name('overview');
    });

    Route::middleware(['role:admin,team_leader'])->prefix('team-leader')->name('team-leader.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'team-leader-access-granted']))->name('overview');
    });
});

require __DIR__.'/settings.php';
