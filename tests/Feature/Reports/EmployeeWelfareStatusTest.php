<?php

use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OperationalCalendar;
use App\Models\OvertimeItem;
use App\Models\OvertimeSubmission;
use App\Models\PolicyThreshold;
use App\Models\Section;
use App\Models\User;
use App\Notifications\FatigueAlertNotification;
use App\Services\FatigueAlertService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function ensureWelfareCalendar(string $date): void
{
    if (! OperationalCalendar::whereDate('calendar_date', $date)->exists()) {
        OperationalCalendar::create([
            'calendar_date' => $date,
            'day_type' => Carbon::parse($date)->isWeekend() ? 'HLR' : 'HKN',
            'is_holiday' => false,
        ]);
    }
}

function seedWelfareApprovedItem(
    Employee $employee,
    string $date,
    float $hours,
): OvertimeSubmission {
    ensureWelfareCalendar($date);

    $user = User::first() ?? User::factory()->admin()->create();

    $submission = OvertimeSubmission::create([
        'submission_code' => 'OT-WELFARE-'.str_replace('-', '', $date).'-'.uniqid(),
        'submission_date' => $date,
        'operational_date' => $date,
        'day_type' => 'HKN',
        'department_id' => $employee->department_id,
        'section_id' => $employee->section_id,
        'submitted_by_user_id' => $user->id,
        'status' => 'APPROVED',
        'total_hours_cached' => $hours,
    ]);

    OvertimeItem::create([
        'overtime_submission_id' => $submission->id,
        'employee_id' => $employee->id,
        'npk_snapshot' => $employee->npk,
        'hours_production' => $hours,
        'hours_tpm' => 0.0,
        'hours_project' => 0.0,
        'hours_others' => 0.0,
        'hourly_rate_snapshot' => 35000.0,
        'total_cost_snapshot' => $hours * 35000.0,
        'status' => 'APPROVED',
    ]);

    return $submission;
}

test('dossier show includes welfare_status with 4-week rolling trend and safety score in Inertia props', function () {
    $dept = Department::factory()->create(['name' => 'Assembly Plant']);
    $section = Section::factory()->create(['department_id' => $dept->id, 'name' => 'Trim Line']);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'full_name' => 'Budi Santoso',
        'npk' => 'EMP-WEL-001',
    ]);

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    // Current date week in September 2026
    seedWelfareApprovedItem($employee, '2026-09-08', 14.0);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $response = $this->actingAs($teamLeader)->get(route('reports.employees.show', [
        'npk' => $employee->npk,
        'year' => 2026,
        'month' => 9,
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('reports/EmployeeDossier')
        ->has('welfare_status')
        ->where('welfare_status.employee_id', $employee->id)
        ->where('welfare_status.is_advisory', true)
        ->where('welfare_status.weekly_limit', fn ($val) => (float) $val === 20.0)
        ->has('welfare_status.rolling_weeks', 4)
        ->has('welfare_status.safety_score_pct')
        ->has('welfare_status.alert_level')
        ->has('welfare_status.badges')
    );
});

test('FatigueAlertService notifies team leader when employee breaches consecutive weeks limit', function () {
    Notification::fake();

    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-FATIGUE-01',
        'full_name' => 'Tono Overload',
    ]);

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'is_active' => true,
    ]);

    // 3 consecutive weeks over limit
    seedWelfareApprovedItem($employee, '2026-09-08', 22.0); // Week 0
    seedWelfareApprovedItem($employee, '2026-09-01', 25.0); // Week -1
    seedWelfareApprovedItem($employee, '2026-08-25', 24.0); // Week -2

    /** @var FatigueAlertService $service */
    $service = app(FatigueAlertService::class);

    $dispatched = $service->evaluateAndNotifyForEmployee(
        employee: $employee,
        year: 2026,
        month: 9,
        referenceDate: '2026-09-08'
    );

    expect($dispatched)->toBeTrue();

    Notification::assertSentTo(
        $teamLeader,
        FatigueAlertNotification::class,
        function (FatigueAlertNotification $notification) use ($employee) {
            return $notification->employee->id === $employee->id
                && $notification->consecutiveWeeks === 3
                && $notification->weeklyHours === 22.0
                && $notification->weeklyLimit === 20.0;
        }
    );
});

test('FatigueAlertService deduplicates notifications within the same calendar month', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'is_active' => true,
    ]);

    seedWelfareApprovedItem($employee, '2026-09-08', 22.0);
    seedWelfareApprovedItem($employee, '2026-09-01', 25.0);
    seedWelfareApprovedItem($employee, '2026-08-25', 24.0);

    /** @var FatigueAlertService $service */
    $service = app(FatigueAlertService::class);

    // First evaluation: dispatches notification to database
    $firstRun = $service->evaluateAndNotifyForEmployee($employee, 2026, 9, '2026-09-08');
    expect($firstRun)->toBeTrue();

    // Verify DB record exists
    $countInDb = DB::table('notifications')
        ->where('type', FatigueAlertNotification::class)
        ->where('data->employee_id', $employee->id)
        ->count();
    expect($countInDb)->toBe(1);

    // Second evaluation in same month: deduplicated, should return false and not insert new row
    $secondRun = $service->evaluateAndNotifyForEmployee($employee, 2026, 9, '2026-09-08');
    expect($secondRun)->toBeFalse();

    $countAfterSecond = DB::table('notifications')
        ->where('type', FatigueAlertNotification::class)
        ->where('data->employee_id', $employee->id)
        ->count();
    expect($countAfterSecond)->toBe(1);
});

test('RecalculateMonthlyBurnSnapshotJob triggers FatigueAlertService post-approval', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create();

    PolicyThreshold::factory()->plantDefault()->create([
        'weekly_soft_limit_hours' => 20.0,
        'consecutive_weeks_alert' => 3,
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create([
        'is_active' => true,
    ]);

    // 3 consecutive weeks over limit
    seedWelfareApprovedItem($employee, '2026-09-08', 23.0);
    seedWelfareApprovedItem($employee, '2026-09-01', 22.0);
    seedWelfareApprovedItem($employee, '2026-08-25', 21.0);

    $job = new RecalculateMonthlyBurnSnapshotJob($section->id, 2026, 9);
    $job->handle();

    // Notification should have been delivered to the team leader
    $notification = $teamLeader->notifications()->first();
    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe(FatigueAlertNotification::class)
        ->and($notification->data['employee_id'])->toBe($employee->id)
        ->and($notification->data['consecutive_weeks'])->toBe(3);
});

test('team leader can fetch fatigue notification via NotificationController and mark it as read', function () {
    $dept = Department::factory()->create();
    $section = Section::factory()->create(['department_id' => $dept->id]);
    $employee = Employee::factory()->forDepartmentAndSection($dept, $section)->create([
        'npk' => 'EMP-NOTIF-99',
        'full_name' => 'Slamet Alert',
    ]);

    $teamLeader = User::factory()->teamLeader($section->id, $dept->id)->create();

    $teamLeader->notify(new FatigueAlertNotification(
        employee: $employee,
        consecutiveWeeks: 3,
        weeklyHours: 24.5,
        weeklyLimit: 20.0,
        fiscalYear: 2026,
        fiscalMonth: 9,
    ));

    $indexRes = $this->actingAs($teamLeader)->getJson(route('notifications.index'));
    $indexRes->assertOk();
    $data = $indexRes->json('notifications');

    expect($indexRes->json('unread_count'))->toBe(1)
        ->and($data)->toHaveCount(1)
        ->and($data[0]['data']['notification_type'])->toBe('fatigue_alert')
        ->and($data[0]['data']['employee_npk'])->toBe('EMP-NOTIF-99');

    $notificationId = $data[0]['id'];

    $markRes = $this->actingAs($teamLeader)->patchJson(route('notifications.read', ['id' => $notificationId]));
    $markRes->assertOk();
    expect($markRes->json('unread_count'))->toBe(0);

    expect($teamLeader->unreadNotifications()->count())->toBe(0);
});
