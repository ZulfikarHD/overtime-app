<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Role-protected routes for authorization verification and testing
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'admin-access-granted']))->name('overview');
    });

    Route::middleware(['role:admin,manager'])->prefix('manager')->name('manager.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'manager-access-granted']))->name('overview');
    });

    Route::middleware(['role:admin,team_leader'])->prefix('team-leader')->name('team-leader.')->group(function () {
        Route::get('/overview', fn () => response()->json(['status' => 'team-leader-access-granted']))->name('overview');
    });
});

require __DIR__.'/settings.php';
