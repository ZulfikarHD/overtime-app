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

    // Linear-ish training set so R² stays ≥ 0.4 → verdict "LAYAK"
    $rows = [
        [2024, 1, 20, 2000, 700, 40000],
        [2024, 2, 21, 2200, 720, 45000],
        [2024, 3, 19, 1800, 680, 36000],
        [2024, 4, 22, 2500, 750, 52000],
        [2024, 5, 20, 2100, 710, 43000],
        [2024, 6, 21, 2300, 730, 48000],
        [2024, 7, 18, 1700, 660, 33000],
        [2024, 8, 22, 2600, 760, 55000],
        [2024, 9, 20, 2050, 705, 42000],
        [2024, 10, 21, 2400, 740, 50000],
    ];

    foreach ($rows as [$year, $month, $workingDays, $volume, $manPower, $index]) {
        MlTrainingData::factory()->create([
            'year' => $year,
            'month' => $month,
            'working_days' => $workingDays,
            'production_volume' => $volume,
            'man_power' => $manPower,
            'overtime_index' => $index,
        ]);
    }

    visit('/login')
        ->fill('email', 'ml.admin.analysis@factory.com')
        ->fill('password', 'password')
        ->click('[data-test="login-button"]')
        ->assertPathIs('/dashboard')
        ->navigate('/analytics/ml/analysis')
        ->assertSee('Model Regresi LAYAK Digunakan')
        ->assertDontSee('Model Regresi BELUM LAYAK')
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
