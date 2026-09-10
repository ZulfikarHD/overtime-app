<?php

use App\Models\Department;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\Section;
use App\Models\User;

test('admin can view predictive analytics tab with 4 kpis, 3 charts, and seasonal summary', function () {
    $dept = Department::create([
        'code' => 'DEPT_PRED_ASY',
        'name' => 'Assembly Predictive Plant',
        'cost_center_code' => 'CC-PRED-ASY',
        'default_hourly_rate' => 50000.00,
        'is_active' => true,
    ]);

    $section = Section::create([
        'department_id' => $dept->id,
        'code' => 'SEC_PRED_TRIM',
        'name' => 'Trim Line Predictive',
        'is_active' => true,
    ]);

    $model = MlModel::create([
        'model_key' => 'demand_forecast_lgbm_v2',
        'algorithm_name' => 'LightGBM Regressor',
        'model_type' => 'DEMAND_FORECAST',
        'version' => 'v2.1.0',
        'metrics' => [
            'mape' => 8.2,
            'rmse' => 14.3,
            'r2' => 0.89,
        ],
        'is_active' => true,
    ]);

    MlPrediction::create([
        'ml_model_id' => $model->id,
        'target_type' => 'SECTION',
        'target_id' => $section->id,
        'prediction_horizon' => 'MONTH_NEXT',
        'predicted_value' => 380.0,
        'confidence_interval_lower' => 350.0,
        'confidence_interval_upper' => 410.0,
        'risk_score' => 0.1200,
        'risk_level' => 'LOW',
        'fallback_used' => false,
    ]);

    $admin = User::factory()->admin()->create([
        'name' => 'Budi Predictive Admin',
        'email' => 'budi.pred@factory.com',
        'password' => 'password',
        'npk' => 'EMP-P9901',
    ]);

    visit('/login')
        ->fill('email', 'budi.pred@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Analytics & Decision')
        ->assertPathIs('/analytics')
        ->assertSee('Overtime Analytics & Decision Intelligence')
        ->assertSee('Predictive Analytics')
        ->assertPresent('[data-test="card-forecast-next-month"]')
        ->assertPresent('[data-test="card-forecast-accuracy"]')
        ->assertPresent('[data-test="card-seasonal-pattern"]')
        ->assertPresent('[data-test="card-trend-direction"]')
        ->assertPresent('[data-test="forecast-bar-chart"]')
        ->assertPresent('[data-test="trend-projection-chart"]')
        ->assertPresent('[data-test="seasonal-pattern-chart"]')
        ->assertPresent('[data-test="seasonal-summary-cards"]');
});

test('manager scoped to department sees moving average baseline on cold start', function () {
    $dept = Department::create([
        'code' => 'DEPT_PRED_MGR',
        'name' => 'Machining Plant MGR',
        'cost_center_code' => 'CC-PRED-MGR',
        'default_hourly_rate' => 52000.00,
        'is_active' => true,
    ]);

    $manager = User::factory()->manager($dept->id)->create([
        'name' => 'Dewi Manager Machining',
        'email' => 'dewi.mgr@factory.com',
        'password' => 'password',
        'npk' => 'EMP-P9902',
    ]);

    visit('/login')
        ->fill('email', 'dewi.mgr@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->click('Analytics & Decision')
        ->assertPathIs('/analytics')
        ->assertSee('Machining Plant MGR')
        ->assertSee('Moving Average')
        ->assertPresent('[data-test="card-forecast-next-month"]')
        ->assertPresent('[data-test="card-forecast-accuracy"]');
});
