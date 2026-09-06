<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\SectionController;
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

        // Department & Section Hierarchy CRUD (E02-01)
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::post('/sections', [SectionController::class, 'store'])->name('sections.store');
        Route::put('/sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('/sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
    });

    Route::middleware(['role:admin,manager'])->prefix('manager')->name('manager.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'manager-access-granted']))->name('overview');
    });

    Route::middleware(['role:admin,team_leader'])->prefix('team-leader')->name('team-leader.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'team-leader-access-granted']))->name('overview');
    });
});

require __DIR__.'/settings.php';
