<?php

use App\Models\Department;
use App\Models\OperationalCalendar;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $todayWib = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $todayWib],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => '2026-09-08'],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
});

test('team leader can open spkl sheet from history table and attach spkl reference number', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_SPKL_BRW',
        'name' => 'Assembly Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_SPKL_TRIM',
        'name' => 'Trim Line',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.trim@factory.com',
        'password' => 'password',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SPKL-TEST-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 4.5,
    ]);

    $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->addDays(2)->toDateString(),
    ]);

    visit('/login')
        ->fill('email', 'tl.trim@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    visit('/overtime/submissions')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('OT-SPKL-TEST-001')
        ->assertSee('Pending')
        // Open SPKL Upload Sheet
        ->click('[data-test="btn-attach-spkl-'.$submission->id.'"]')
        ->waitForText('Attach SPKL Document')
        ->assertSee('OT-SPKL-TEST-001')
        ->assertSee('Physical SPKL Document Number')
        // Input SPKL physical registration number
        ->fill('[data-test="input-spkl-number"]', 'SPKL/ASMY/2026/09/777')
        ->click('[data-test="btn-submit-spkl"]')
        // Wait for sheet to close and status badge to update to Attached
        ->waitForText('Attached');

    $spkl = $submission->fresh()->spklDocument;
    expect($spkl->status)->toBe('ATTACHED');
    expect($spkl->spkl_number)->toBe('SPKL/ASMY/2026/09/777');
});

test('manager can open detail modal and verify attached spkl document', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_MGR_SPKL',
        'name' => 'Engine Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_MGR_BLOCK',
        'name' => 'Cylinder Block Line',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create();
    $manager = User::factory()->manager($dept->id)->create([
        'email' => 'mgr.engine@factory.com',
        'password' => 'password',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-SPKL-VERIFY-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 6.0,
    ]);

    $submission->spklDocument()->create([
        'status' => 'ATTACHED',
        'spkl_number' => 'SPKL/ENG/2026/09/101',
        'due_date' => Carbon::now('Asia/Jakarta')->addDays(1)->toDateString(),
        'attached_at' => Carbon::now('Asia/Jakarta'),
        'attached_by_user_id' => $tl->id,
    ]);

    visit('/login')
        ->fill('email', 'mgr.engine@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    visit('/overtime/submissions')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('OT-SPKL-VERIFY-001')
        ->assertSee('Attached')
        // Open Detail Modal
        ->click('[data-test="btn-detail-'.$submission->id.'"]')
        ->waitForText('SPKL: Document Attached')
        ->assertSee('SPKL/ENG/2026/09/101')
        // Manager clicks Verifikasi SPKL button
        ->click('[data-test="btn-modal-verify-spkl"]')
        ->waitForText('SPKL: Verified by Manager');

    $spkl = $submission->fresh()->spklDocument;
    expect($spkl->status)->toBe('VERIFIED');
});

test('team leader can cancel and close spkl upload sheet without submitting', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_CARD_SPKL',
        'name' => 'Stamping Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_CARD_PRESS',
        'name' => 'Press Line A',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.card@factory.com',
        'password' => 'password',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-CARD-TEST-001',
        'submission_date' => '2026-09-08',
        'operational_date' => '2026-09-08',
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 5.0,
    ]);

    $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->addDays(2)->toDateString(),
    ]);

    visit('/login')
        ->fill('email', 'tl.card@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard');

    visit('/overtime/submissions')
        ->assertPathIs('/overtime/submissions')
        ->assertSee('OT-CARD-TEST-001')
        ->click('[data-test="btn-attach-spkl-'.$submission->id.'"]')
        ->waitForText('Attach SPKL Document')
        ->assertSee('OT-CARD-TEST-001')
        ->click('[data-test="btn-cancel-spkl"]')
        ->assertDontSee('Physical SPKL Document Number');
});
