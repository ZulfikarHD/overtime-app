<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\SplEntry;
use App\Models\User;
use App\Support\Features;

test('overtime submission auto-approves when approvals feature is disabled', function () {
    config([
        'features.overtime_approvals_enabled' => false,
        'features.capex_attribution_required' => false,
    ]);

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    $this->actingAs($teamLeader)->post(route('overtime.submissions.store'), [
        'operational_date' => '2026-09-08',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'items' => [
            [
                'employee_id' => $emp->id,
                'hours_production' => 2.0,
            ],
        ],
    ])->assertRedirect();

    expect(OvertimeSubmission::first()->status)->toBe('APPROVED')
        ->and(OvertimeItem::first()->status)->toBe('APPROVED');
});

test('spl import marks entries approved when approvals feature is disabled', function () {
    config(['features.overtime_approvals_enabled' => false]);

    expect(Features::overtimeApprovalsEnabled())->toBeFalse();

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $admin = User::factory()->admin()->create();
    $emp = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => '12345',
    ]);

    $data = [
        'employee_id' => $emp->id,
        'npk_snapshot' => '12345',
        'employee_name_snapshot' => $emp->full_name,
        'section_id' => $section->id,
        'department_id' => $dept->id,
        'realization_date' => '2026-09-08',
        'day_type' => 'HKN',
        'start_time' => '16:00',
        'end_time' => '18:00',
        'total_hours' => 2.00,
        'imported_by_user_id' => $admin->id,
    ];

    if (! Features::overtimeApprovalsEnabled()) {
        $data['status'] = 'APPROVED';
        $data['reviewed_by_user_id'] = $admin->id;
        $data['reviewed_at'] = now('Asia/Jakarta');
    }

    $entry = SplEntry::create($data);

    expect($entry->fresh()->status)->toBe('APPROVED')
        ->and($entry->fresh()->reviewed_by_user_id)->toBe($admin->id);
});
