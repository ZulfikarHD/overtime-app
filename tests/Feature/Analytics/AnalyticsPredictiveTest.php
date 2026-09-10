<?php

use App\Models\Department;
use App\Models\MlModel;
use App\Models\MlPrediction;
use App\Models\Section;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guest is redirected to login when accessing predictive endpoint', function () {
    $this->get(route('analytics.predictive'))->assertRedirect(route('login'));
});

test('operator and team leader cannot access predictive endpoint', function () {
    $operator = User::factory()->user()->create();
    $teamLeader = User::factory()->teamLeader()->create();

    $this->actingAs($operator)
        ->get(route('analytics.predictive'))
        ->assertForbidden();

    $this->actingAs($teamLeader)
        ->get(route('analytics.predictive'))
        ->assertForbidden();
});

test('admin can access predictive endpoint and receives complete predictive structure', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Assembly Department', 'is_active' => true]);
    $sec1 = Section::factory()->create(['department_id' => $dept->id, 'code' => 'ASY-01', 'name' => 'Trim Line', 'is_active' => true]);
    $sec2 = Section::factory()->create(['department_id' => $dept->id, 'code' => 'ASY-02', 'name' => 'Chassis Line', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.predictive'));

    $response->assertOk();
    $response->assertJsonStructure([
        'kpi' => [
            'predicted_hours',
            'ci_lower',
            'ci_upper',
            'margin',
            'formatted_prediction',
            'model_name',
            'fallback_used',
            'accuracy_rate',
            'accuracy_label',
            'accuracy_description',
            'seasonal_pattern',
            'seasonal_description',
            'trend_direction',
            'trend_label',
            'trend_growth_rate_pct',
            'trend_description',
        ],
        'section_forecast' => [
            'sections',
            'chart_data' => [
                'labels',
                'datasets',
            ],
        ],
        'trend_projection' => [
            'labels',
            'historical_series',
            'projected_series',
            'ci_lower_series',
            'ci_upper_series',
        ],
        'seasonal_pattern' => [
            'labels',
            'monthly_averages',
            'grand_average',
            'peak_quarter',
            'peak_quarter_index',
        ],
        'seasonal_summary' => [
            'peak_season' => ['month', 'value', 'variance_pct'],
            'low_season' => ['month', 'value', 'variance_pct'],
            'cycle_pattern',
        ],
        'scope' => [
            'department_id',
            'department_name',
            'target_month_name',
            'is_cold_start',
        ],
    ]);
});

test('manager is strictly scoped to their assigned department in predictive endpoint', function () {
    $deptA = Department::factory()->create(['name' => 'Department A', 'is_active' => true]);
    $deptB = Department::factory()->create(['name' => 'Department B', 'is_active' => true]);

    $secA = Section::factory()->create(['department_id' => $deptA->id, 'code' => 'SEC-A1', 'is_active' => true]);
    $secB = Section::factory()->create(['department_id' => $deptB->id, 'code' => 'SEC-B1', 'is_active' => true]);

    $manager = User::factory()->manager($deptA->id)->create();

    // Manager attempts to query Department B
    $response = $this->actingAs($manager)->get(route('analytics.predictive', [
        'department_id' => $deptB->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    // Scope must remain Department A
    expect($data['scope']['department_id'])->toBe($deptA->id)
        ->and($data['scope']['department_name'])->toBe('Department A');

    $sectionCodes = collect($data['section_forecast']['sections'])->pluck('section_code')->all();
    expect($sectionCodes)->toContain('SEC-A1')
        ->and($sectionCodes)->not->toContain('SEC-B1');
});

test('predictive service uses active ML model predictions when available', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Machining Dept', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'MCH-01', 'is_active' => true]);

    $mlModel = MlModel::create([
        'model_key' => 'DEMAND_FORECAST_V1',
        'model_type' => 'DEMAND_FORECAST',
        'version' => '1.0.0',
        'algorithm_name' => 'Quantile LightGBM',
        'hyperparameters' => ['learning_rate' => 0.05],
        'metrics' => ['mape' => 8.2],
        'is_active' => true,
        'trained_at' => now(),
    ]);

    MlPrediction::create([
        'ml_model_id' => $mlModel->id,
        'target_type' => 'SECTION',
        'target_id' => $sec->id,
        'prediction_horizon' => 'MONTH_NEXT',
        'predicted_value' => 450.50,
        'confidence_interval_lower' => 420.00,
        'confidence_interval_upper' => 481.00,
        'risk_score' => 0.12,
        'risk_level' => 'LOW',
        'fallback_used' => false,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('analytics.predictive', [
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['kpi']['fallback_used'])->toBeFalse()
        ->and($data['kpi']['model_name'])->toBe('Quantile LightGBM')
        ->and($data['kpi']['accuracy_rate'])->toEqual(91.8)
        ->and($data['kpi']['accuracy_label'])->toBe('91.8%')
        ->and($data['kpi']['predicted_hours'])->toEqual(450.5)
        ->and($data['kpi']['ci_lower'])->toEqual(420.0)
        ->and($data['kpi']['ci_upper'])->toEqual(481.0);
});

test('predictive service falls back to moving average gracefully when no ML predictions exist', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Stamping Dept', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'STP-01', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.predictive', [
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data['kpi']['fallback_used'])->toBeTrue()
        ->and($data['kpi']['model_name'])->toBe('Model Baseline')
        ->and($data['kpi']['accuracy_label'])->toBe('Moving Average')
        ->and($data['scope']['is_cold_start'])->toBeTrue();
});

test('inertia analytics page contains predictiveData prop on tab predictive', function () {
    $admin = User::factory()->admin()->create();

    $dept = Department::factory()->create(['name' => 'Welding Dept', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'WLD-01', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.index', ['tab' => 'predictive']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Analytics/Index')
        ->where('currentTab', 'predictive')
        ->has('predictiveData', fn (Assert $predAssert) => $predAssert
            ->has('kpi')
            ->has('section_forecast')
            ->has('trend_projection')
            ->has('seasonal_pattern')
            ->has('seasonal_summary')
            ->has('scope')
            ->etc()
        )
    );
});

test('exporting predictive tab as csv includes predictive indicators and section breakdown', function () {
    $admin = User::factory()->admin()->create();
    $dept = Department::factory()->create(['name' => 'Body Assembly', 'is_active' => true]);
    $sec = Section::factory()->create(['department_id' => $dept->id, 'code' => 'ASY-B1', 'is_active' => true]);

    $response = $this->actingAs($admin)->get(route('analytics.export', [
        'tab' => 'predictive',
        'format' => 'csv',
        'department_id' => $dept->id,
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    // Stream download content check
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toContain('INDIKATOR PREDIKSI LEMBUR')
        ->and($content)->toContain('RINCIAN PREDIKSI PER SEKSI')
        ->and($content)->toContain('ASY-B1');
});
