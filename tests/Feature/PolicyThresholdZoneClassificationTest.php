<?php

use App\Enums\BurnZone;
use App\Models\Department;
use App\Models\PolicyThreshold;
use App\Services\PolicyThresholdService;

test('plant default is created with 70 and 90 percent zone boundaries', function () {
    $default = app(PolicyThresholdService::class)->getPlantDefault();

    expect((float) $default->green_max_pct)->toBe(70.0)
        ->and((float) $default->yellow_max_pct)->toBe(90.0);
});

test('classifies against the plant default when a department has no override', function () {
    $department = Department::factory()->create();
    $service = app(PolicyThresholdService::class);

    expect($service->classify(70.0, $department->id))->toBe(BurnZone::Green)
        ->and($service->classify(80.0, $department->id))->toBe(BurnZone::Yellow)
        ->and($service->classify(95.0, $department->id))->toBe(BurnZone::Red)
        ->and($service->classify(95.0))->toBe(BurnZone::Red);
});

test('department override boundaries take precedence over the plant default', function () {
    $department = Department::factory()->create();
    PolicyThreshold::factory()->plantDefault()->create();
    PolicyThreshold::factory()->forDepartment($department)->create([
        'green_max_pct' => 85.0,
        'yellow_max_pct' => 100.0,
    ]);
    $service = app(PolicyThresholdService::class);

    expect($service->classify(80.0, $department->id))->toBe(BurnZone::Green)
        ->and($service->classify(95.0, $department->id))->toBe(BurnZone::Yellow)
        ->and($service->classify(101.0, $department->id))->toBe(BurnZone::Red)
        ->and($service->classify(80.0))->toBe(BurnZone::Yellow)
        ->and($service->boundariesFor($department->id))->toBe([
            'green_max_pct' => 85.0,
            'yellow_max_pct' => 100.0,
        ]);
});
