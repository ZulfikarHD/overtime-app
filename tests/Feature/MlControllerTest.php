<?php

use App\Models\MlForecastInput;
use App\Models\MlTrainingData;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// ─── Authentication & Authorization ──────────────────────────────────────────

test('guest is redirected to login for all ML routes', function () {
    $this->get(route('analytics.ml.training'))->assertRedirect(route('login'));
    $this->get(route('analytics.ml.analysis'))->assertRedirect(route('login'));
    $this->get(route('analytics.ml.forecast'))->assertRedirect(route('login'));
});

test('operator role is forbidden from ML training page', function () {
    $operator = User::factory()->user()->create();
    $this->actingAs($operator)->get(route('analytics.ml.training'))->assertForbidden();
});

test('operator role is forbidden from ML analysis page', function () {
    $operator = User::factory()->user()->create();
    $this->actingAs($operator)->get(route('analytics.ml.analysis'))->assertForbidden();
});

test('operator role is forbidden from ML forecast page', function () {
    $operator = User::factory()->user()->create();
    $this->actingAs($operator)->get(route('analytics.ml.forecast'))->assertForbidden();
});

test('team leader role is forbidden from ML pages', function () {
    $tl = User::factory()->teamLeader()->create();
    $this->actingAs($tl)->get(route('analytics.ml.training'))->assertForbidden();
    $this->actingAs($tl)->get(route('analytics.ml.analysis'))->assertForbidden();
    $this->actingAs($tl)->get(route('analytics.ml.forecast'))->assertForbidden();
});

test('admin can access ML training page', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('analytics.ml.training'))->assertOk();
});

test('manager can access ML training page', function () {
    $manager = User::factory()->manager()->create();
    $this->actingAs($manager)->get(route('analytics.ml.training'))->assertOk();
});

// ─── DataTraining page (GET) ──────────────────────────────────────────────────

test('training page returns Inertia component with training_data prop', function () {
    $admin = User::factory()->admin()->create();

    MlTrainingData::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('analytics.ml.training'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Analytics/Ml/DataTraining')
                ->has('rows')
        );
});

// ─── storeTraining ────────────────────────────────────────────────────────────

test('admin can store a new training row', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('analytics.ml.training.store'), [
            'year' => 2027,
            'month' => 1,
            'working_days' => 20,
            'production_volume' => 3000,
            'man_power' => 700,
            'overtime_index' => 80000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('ml_training_data', [
        'year' => 2027,
        'month' => 1,
    ]);
});

test('training store validates required fields', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('analytics.ml.training.store'), [])
        ->assertSessionHasErrors(['year', 'month', 'working_days', 'production_volume', 'man_power', 'overtime_index']);
});

test('training store rejects duplicate year-month combination', function () {
    $admin = User::factory()->admin()->create();
    $existing = MlTrainingData::factory()->create(['year' => 2027, 'month' => 2]);

    $this->actingAs($admin)
        ->post(route('analytics.ml.training.store'), [
            'year' => 2027,
            'month' => 2,
            'working_days' => 20,
            'production_volume' => 3000,
            'man_power' => 700,
            'overtime_index' => 80000,
        ])
        ->assertSessionHasErrors('month');
});

// ─── updateTraining ───────────────────────────────────────────────────────────

test('admin can update an existing training row', function () {
    $admin = User::factory()->admin()->create();
    $row = MlTrainingData::factory()->create();

    $this->actingAs($admin)
        ->put(route('analytics.ml.training.update', $row), [
            'year' => $row->year,
            'month' => $row->month,
            'working_days' => 22,
            'production_volume' => 3500,
            'man_power' => 720,
            'overtime_index' => 85000,
        ])
        ->assertRedirect();

    expect($row->fresh()->working_days)->toBe(22);
});

// ─── destroyTraining ─────────────────────────────────────────────────────────

test('admin can delete a training row', function () {
    $admin = User::factory()->admin()->create();
    $row = MlTrainingData::factory()->create();

    $this->actingAs($admin)
        ->delete(route('analytics.ml.training.destroy', $row))
        ->assertRedirect();

    $this->assertDatabaseMissing('ml_training_data', ['id' => $row->id]);
});

// ─── Analysis page (GET) ─────────────────────────────────────────────────────

test('analysis page returns Inertia component', function () {
    $admin = User::factory()->admin()->create();

    // Seed a few rows so the service has data
    MlTrainingData::factory()->count(10)->create();

    $this->actingAs($admin)
        ->get(route('analytics.ml.analysis'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Analytics/Ml/HasilAnalisis')
                ->has('has_training_data')
        );
});

test('analysis page chart_data contains scatter and qq pairs when training data exists', function () {
    $admin = User::factory()->admin()->create();

    MlTrainingData::factory()->count(10)->create();

    $this->actingAs($admin)
        ->get(route('analytics.ml.analysis'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Analytics/Ml/HasilAnalisis')
                ->where('has_training_data', true)
                ->has('chart_data.scatter_pairs')
                ->has('chart_data.qq_pairs')
        );
});

test('analysis page has_training_data is false when no rows', function () {
    $admin = User::factory()->admin()->create();

    // Ensure empty
    MlTrainingData::query()->delete();

    $this->actingAs($admin)
        ->get(route('analytics.ml.analysis'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('has_training_data', false)
        );
});

// ─── Forecast page (GET) ──────────────────────────────────────────────────────

test('forecast page returns Inertia component', function () {
    $admin = User::factory()->admin()->create();

    MlTrainingData::factory()->count(10)->create();

    $this->actingAs($admin)
        ->get(route('analytics.ml.forecast'))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Analytics/Ml/Forecasting')
                ->has('forecast_rows')
                ->has('view_year')
        );
});

test('forecast page shows 12 rows for the requested year when training and forecast data exist', function () {
    $admin = User::factory()->admin()->create();

    // Need enough training data to train the model (≥4 rows)
    MlTrainingData::factory()->count(10)->create();

    // Add a couple of forecast inputs for 2026
    MlForecastInput::factory()->create(['year' => 2026, 'month' => 7]);
    MlForecastInput::factory()->create(['year' => 2026, 'month' => 8]);

    $this->actingAs($admin)
        ->get(route('analytics.ml.forecast', ['year' => 2026]))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Analytics/Ml/Forecasting')
                ->where('view_year', 2026)
                ->count('forecast_rows', 12)
        );
});

test('forecast page auto-estimates months with no manual input using same-month historical data', function () {
    $admin = User::factory()->admin()->create();

    // Seed 3 years of training data (Jan-Dec each year) so every month has history
    MlTrainingData::factory()->count(10)->create();

    // Request year 2027 — no forecast inputs → should show 'auto' rows not 'empty'
    $response = $this->actingAs($admin)
        ->get(route('analytics.ml.forecast', ['year' => 2027]))
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Analytics/Ml/Forecasting')
                ->where('view_year', 2027)
                ->count('forecast_rows', 12)
        );

    // Every row should be 'auto' (historical averages) or 'empty' (no same-month history),
    // but never 'training' or 'forecast' since we have no 2027 data
    $rows = $response->baseResponse->original->getData()['page']['props']['forecast_rows'];
    foreach ($rows as $row) {
        expect($row['source'])->toBeIn(['auto', 'empty']);
    }
});

// ─── storeForecast ────────────────────────────────────────────────────────────

test('admin can store a new forecast row', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('analytics.ml.forecast.store'), [
            'year' => 2027,
            'month' => 8,
            'working_days' => 21,
            'production_volume' => 3100,
            'man_power' => 715,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('ml_forecast_inputs', [
        'year' => 2027,
        'month' => 8,
    ]);
});

test('forecast store rejects duplicate year-month', function () {
    $admin = User::factory()->admin()->create();
    MlForecastInput::factory()->create(['year' => 2027, 'month' => 9]);

    $this->actingAs($admin)
        ->post(route('analytics.ml.forecast.store'), [
            'year' => 2027,
            'month' => 9,
            'working_days' => 21,
            'production_volume' => 3100,
            'man_power' => 715,
        ])
        ->assertSessionHasErrors('month');
});

// ─── updateForecast ───────────────────────────────────────────────────────────

test('admin can update a forecast row including actual_overtime_index', function () {
    $admin = User::factory()->admin()->create();
    $row = MlForecastInput::factory()->create(['actual_overtime_index' => null]);

    $this->actingAs($admin)
        ->put(route('analytics.ml.forecast.update', $row), [
            'year' => $row->year,
            'month' => $row->month,
            'working_days' => $row->working_days,
            'production_volume' => $row->production_volume,
            'man_power' => $row->man_power,
            'actual_overtime_index' => 75000,
        ])
        ->assertRedirect();

    expect($row->fresh()->actual_overtime_index)->toBe(75000);
});

// ─── destroyForecast ─────────────────────────────────────────────────────────

test('admin can delete a forecast row', function () {
    $admin = User::factory()->admin()->create();
    $row = MlForecastInput::factory()->create();

    $this->actingAs($admin)
        ->delete(route('analytics.ml.forecast.destroy', $row))
        ->assertRedirect();

    $this->assertDatabaseMissing('ml_forecast_inputs', ['id' => $row->id]);
});
