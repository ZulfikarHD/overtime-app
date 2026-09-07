<?php

use App\Actions\Overtime\SubmitOvertimeAction;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeItem;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('snapshot does not change after rate update', function () {
    $dept = Department::factory()->create([
        'default_hourly_rate' => 30000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-TEST-01',
        'hourly_rate' => 45000.00,
    ]);

    /** @var SubmitOvertimeAction $action */
    $action = app(SubmitOvertimeAction::class);

    $payload = [
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submission_notes' => 'Test Batch',
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_production' => 3.5,
                'hours_tpm' => 0.0,
                'hours_project' => 0.0,
                'hours_others' => 0.0,
            ],
        ],
    ];

    $submission = $action->execute($payload, $teamLeader->id);
    $item = OvertimeItem::where('overtime_submission_id', $submission->id)->first();

    expect((float) $item->hourly_rate_snapshot)->toBe(45000.00)
        ->and((float) $item->total_cost_snapshot)->toBe(157500.00); // 3.5 * 45000 = 157500

    // Employee gets a wage increase to 65,000 / hr
    $emp->update(['hourly_rate' => 65000.00]);

    // Snapshot on existing item MUST remain strictly identical
    $item->refresh();
    expect((float) $item->hourly_rate_snapshot)->toBe(45000.00)
        ->and((float) $item->total_cost_snapshot)->toBe(157500.00);
});

test('snapshot uses department default rate when employee rate is null', function () {
    $dept = Department::factory()->create([
        'default_hourly_rate' => 32500.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => null, // null rate should fall back to department default
    ]);

    /** @var SubmitOvertimeAction $action */
    $action = app(SubmitOvertimeAction::class);

    $submission = $action->execute([
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_production' => 2.0,
                'hours_tpm' => 0.5,
            ],
        ],
    ], $teamLeader->id);

    $item = OvertimeItem::where('overtime_submission_id', $submission->id)->first();

    // 2.5 hours * 32,500.00 = 81,250.00
    expect((float) $item->hourly_rate_snapshot)->toBe(32500.00)
        ->and((float) $item->total_cost_snapshot)->toBe(81250.00);
});

test('snapshot precision bcmul handles fractional hours and rates', function () {
    $dept = Department::factory()->create([
        'default_hourly_rate' => 35000.00,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 37250.75,
    ]);

    /** @var SubmitOvertimeAction $action */
    $action = app(SubmitOvertimeAction::class);

    $submission = $action->execute([
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_production' => 1.5,
                'hours_tpm' => 1.0,
                'hours_project' => 0.0,
                'hours_others' => 0.5, // Total = 3.00 hours
            ],
        ],
    ], $teamLeader->id);

    $item = OvertimeItem::where('overtime_submission_id', $submission->id)->first();

    // 3.00 * 37250.75 = 111,752.25
    expect($item->hourly_rate_snapshot)->toBe('37250.75')
        ->and($item->total_cost_snapshot)->toBe('111752.25');
});

test('hourly rate snapshot is strictly immutable on existing records', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 40000.00,
    ]);

    /** @var SubmitOvertimeAction $action */
    $action = app(SubmitOvertimeAction::class);

    $submission = $action->execute([
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            ['employee_id' => $emp->id, 'hours_production' => 1.5],
        ],
    ], $teamLeader->id);

    $item = OvertimeItem::where('overtime_submission_id', $submission->id)->first();

    expect(fn () => $item->update(['hourly_rate_snapshot' => 99999.00]))
        ->toThrow(RuntimeException::class, 'The hourly_rate_snapshot is immutable and cannot be modified.');
});

test('total cost snapshot is strictly immutable on existing records', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 40000.00,
    ]);

    /** @var SubmitOvertimeAction $action */
    $action = app(SubmitOvertimeAction::class);

    $submission = $action->execute([
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            ['employee_id' => $emp->id, 'hours_production' => 1.5],
        ],
    ], $teamLeader->id);

    $item = OvertimeItem::where('overtime_submission_id', $submission->id)->first();

    expect(fn () => $item->update(['total_cost_snapshot' => 99999.00]))
        ->toThrow(RuntimeException::class, 'The total_cost_snapshot is immutable and cannot be modified.');
});

test('npk snapshot is strictly immutable on existing records', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'ISZ-9901',
    ]);

    /** @var SubmitOvertimeAction $action */
    $action = app(SubmitOvertimeAction::class);

    $submission = $action->execute([
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            ['employee_id' => $emp->id, 'hours_production' => 1.0],
        ],
    ], $teamLeader->id);

    $item = OvertimeItem::where('overtime_submission_id', $submission->id)->first();

    expect(fn () => $item->update(['npk_snapshot' => 'ISZ-MUTATED']))
        ->toThrow(RuntimeException::class, 'The npk_snapshot is immutable and cannot be modified.');
});

test('snapshot with explicit zero hourly rate uses zero instead of department fallback', function () {
    $dept = Department::factory()->create([
        'default_hourly_rate' => 35000.00,
    ]);

    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'hourly_rate' => 0.00, // Explicit zero rate (not null)
    ]);

    /** @var SubmitOvertimeAction $action */
    $action = app(SubmitOvertimeAction::class);

    $submission = $action->execute([
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            ['employee_id' => $emp->id, 'hours_production' => 2.0],
        ],
    ], $teamLeader->id);

    $item = OvertimeItem::where('overtime_submission_id', $submission->id)->first();

    expect((float) $item->hourly_rate_snapshot)->toBe(0.00)
        ->and((float) $item->total_cost_snapshot)->toBe(0.00);
});
