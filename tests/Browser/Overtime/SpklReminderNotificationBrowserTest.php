<?php

use App\Models\Department;
use App\Models\OperationalCalendar;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use App\Notifications\SpklOverdueNotification;
use App\Notifications\SpklPreDueNotification;
use Carbon\Carbon;

beforeEach(function () {
    $todayWib = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $todayWib],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
});

test('team leader sees unread notification badge and can view overdue spkl in topbar popover', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_NOTIF_BRW',
        'name' => 'Machining Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_NOTIF_MILL',
        'name' => 'Milling Line',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.notif@factory.com',
        'password' => 'password',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-NOTIF-BROWSER-001',
        'submission_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 6.0,
    ]);

    $spkl = $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $tl->notify(new SpklOverdueNotification($submission, $spkl, 2));

    visit('/login')
        ->fill('email', 'tl.notif@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Notification bell badge is visible with count 1
        ->assertSee('1')
        // Open notification popover
        ->click('[data-test="notification-bell-btn"]')
        ->waitForText('OT-NOTIF-BROWSER-001')
        ->assertSee('OT-NOTIF-BROWSER-001')
        ->assertSee('Milling Line');
});

test('team leader can open spkl sheet directly from notification and attach document (User Journey 5)', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_NOTIF_J5',
        'name' => 'Stamping Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_NOTIF_PR1',
        'name' => 'Press Line 1',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.journey5@factory.com',
        'password' => 'password',
    ]);

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-JRN5-TEST-002',
        'submission_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 7.5,
    ]);

    $spkl = $submission->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(1)->toDateString(),
    ]);

    $tl->notify(new SpklOverdueNotification($submission, $spkl, 1));

    visit('/login')
        ->fill('email', 'tl.journey5@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Open notification popover
        ->click('[data-test="notification-bell-btn"]')
        ->waitForText('OT-JRN5-TEST-002')
        // Click Lampirkan on the notification card
        ->click('[data-test="btn-attach-from-notif"]')
        // SPKL sheet slides in
        ->waitForText('OT-JRN5-TEST-002')
        ->fill('[data-test="input-spkl-number"]', 'SPKL/JRN5/2026/09/999')
        ->click('[data-test="btn-submit-spkl"]');

    // Confirm SPKL status updated in DB
    $freshSpkl = $submission->fresh()->spklDocument;
    expect($freshSpkl->status)->toBe('ATTACHED')
        ->and($freshSpkl->spkl_number)->toBe('SPKL/JRN5/2026/09/999');
});

test('team leader can mark single notification as read and mark all as read from popover', function () {
    $dept = Department::factory()->create([
        'code' => 'DEPT_NOTIF_CLR',
        'name' => 'Engine Plant',
        'is_active' => true,
    ]);

    $section = Section::factory()->create([
        'department_id' => $dept->id,
        'code' => 'SEC_NOTIF_ENG',
        'name' => 'Assembly Engine Line',
        'is_active' => true,
    ]);

    $tl = User::factory()->teamLeader($section->id, $dept->id)->create([
        'email' => 'tl.clear@factory.com',
        'password' => 'password',
    ]);

    $submission1 = OvertimeSubmission::create([
        'submission_code' => 'OT-CLR-001',
        'submission_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 4.0,
    ]);
    $spkl1 = $submission1->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(1)->toDateString(),
    ]);

    $submission2 = OvertimeSubmission::create([
        'submission_code' => 'OT-CLR-002',
        'submission_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 5.0,
    ]);
    $spkl2 = $submission2->spklDocument()->create([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->addDay()->toDateString(),
    ]);

    $tl->notify(new SpklOverdueNotification($submission1, $spkl1, 1));
    $tl->notify(new SpklPreDueNotification($submission2, $spkl2));

    expect($tl->unreadNotifications()->count())->toBe(2);

    visit('/login')
        ->fill('email', 'tl.clear@factory.com')
        ->fill('password', 'password')
        ->click('Log in to System')
        ->assertPathIs('/dashboard')
        // Bell badge shows 2
        ->assertSee('2')
        // Open popover
        ->click('[data-test="notification-bell-btn"]')
        ->waitForText('OT-CLR-001')
        ->assertSee('OT-CLR-002')
        // Click Mark All As Read
        ->click('[data-test="btn-mark-all-read"]')
        // Wait for empty state message
        ->waitForText('No new notifications')
        ->assertDontSee('OT-CLR-001');

    expect($tl->fresh()->unreadNotifications()->count())->toBe(0);
});
