<?php

use App\Models\MlForecastInput;
use App\Models\MlTrainingData;
use App\Models\User;

// ─── Navigation ────────────────────────────────────────────────────────────────

test('admin can navigate to ML Data Training page', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.nav@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-001',
    ]);

    visit('/login')
        ->fill('email', 'ml.admin.nav@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->visit('/analytics/ml/training')
        ->assertPathIs('/analytics/ml/training')
        ->assertSee('Data Training');
});

test('admin can navigate between all three ML pages via tab links', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.tabs@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-002',
    ]);

    MlTrainingData::factory()->count(10)->create();

    visit('/analytics/ml/training')
        ->actingAs($admin)
        ->assertSee('Data Training')
        ->click('Hasil Analisis')
        ->assertPathIs('/analytics/ml/analysis')
        ->assertSee('Hasil Analisis')
        ->click('Analisis Forecasting')
        ->assertPathIs('/analytics/ml/forecast')
        ->assertSee('Analisis Forecasting');
});

// ─── Data Training page ────────────────────────────────────────────────────────

test('training page shows empty state when no data exists', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.empty@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-003',
    ]);

    MlTrainingData::query()->delete();

    visit('/analytics/ml/training')
        ->actingAs($admin)
        ->assertSee('Belum ada data training');
});

test('admin can open and fill the add training data sheet', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.add@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-004',
    ]);

    visit('/analytics/ml/training')
        ->actingAs($admin)
        ->click('Tambah Data Training')
        ->waitForText('Tambah Data Baru')
        ->assertSee('X1 — Hari Kerja')
        ->assertSee('X2 — Volume Produksi')
        ->assertSee('X3 — Man Power')
        ->assertSee('Y — Index Overtime');
});

// ─── Hasil Analisis page ───────────────────────────────────────────────────────

test('analysis page shows model summary verdict when training data exists', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.analysis@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-005',
    ]);

    MlTrainingData::factory()->count(10)->create();

    visit('/analytics/ml/analysis')
        ->actingAs($admin)
        ->assertSee('Persamaan Regresi')
        ->assertSee('R²');
});

test('analysis page shows empty state when no training data', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.nodata@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-006',
    ]);

    MlTrainingData::query()->delete();

    visit('/analytics/ml/analysis')
        ->actingAs($admin)
        ->assertSee('Data training belum tersedia');
});

// ─── Forecasting page ─────────────────────────────────────────────────────────

test('forecasting page shows 12-month table for 2026 with training and forecast rows', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.forecast@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-007',
    ]);

    MlTrainingData::factory()->count(10)->create();
    MlForecastInput::factory()->create(['year' => 2026, 'month' => 7]);

    visit('/analytics/ml/forecast?year=2026')
        ->actingAs($admin)
        ->assertSee('Januari')
        ->assertSee('Juli')
        ->assertSee('Desember')
        ->assertSee('Tabel Januari–Desember 2026');
});

test('forecasting page shows auto-estimates for 2027 without any manual input', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.auto@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-008',
    ]);

    MlTrainingData::factory()->count(10)->create();

    visit('/analytics/ml/forecast?year=2027')
        ->actingAs($admin)
        ->assertSee('Auto-Estimasi')
        ->assertSee('Tabel Januari–Desember 2027');
});

test('admin can switch year on forecasting page', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'ml.admin.yearsw@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-009',
    ]);

    MlTrainingData::factory()->count(10)->create();

    visit('/analytics/ml/forecast?year=2026')
        ->actingAs($admin)
        ->assertSee('2027')
        ->click('2027')
        ->assertPathIs('/analytics/ml/forecast')
        ->assertSee('Tabel Januari–Desember 2027');
});

// ─── Authorization ─────────────────────────────────────────────────────────────

test('regular employee cannot access ML analytics pages', function () {
    $employee = User::factory()->create([
        'email' => 'ml.regular@factory.com',
        'password' => 'password',
        'npk' => 'EMP-ML-010',
    ]);

    visit('/analytics/ml/training')
        ->actingAs($employee)
        ->assertStatus(403);
});
