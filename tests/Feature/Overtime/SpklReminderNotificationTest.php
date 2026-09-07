<?php

use App\Jobs\SendSpklReminderJob;
use App\Models\Department;
use App\Models\OperationalCalendar;
use App\Models\OvertimeSubmission;
use App\Models\Section;
use App\Models\User;
use App\Notifications\SpklOverdueNotification;
use App\Notifications\SpklPreDueNotification;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $todayWib = Carbon::now('Asia/Jakarta')->format('Y-m-d');
    OperationalCalendar::firstOrCreate(
        ['calendar_date' => $todayWib],
        ['day_type' => 'HKN', 'is_holiday' => false]
    );
});

function createReminderTestSubmission(array $submissionAttrs = [], array $spklAttrs = [], ?User $user = null): OvertimeSubmission
{
    $dept = Department::factory()->create(['is_active' => true]);
    $section = Section::factory()->create(['department_id' => $dept->id, 'is_active' => true]);
    $tl = $user ?? User::factory()->teamLeader($section->id, $dept->id)->create();

    $submission = OvertimeSubmission::create(array_merge([
        'submission_code' => 'OT-NOTIF-'.uniqid(),
        'submission_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'operational_date' => Carbon::now('Asia/Jakarta')->toDateString(),
        'day_type' => 'HKN',
        'department_id' => $dept->id,
        'section_id' => $section->id,
        'submitted_by_user_id' => $tl->id,
        'status' => 'SUBMITTED',
        'total_hours_cached' => 8.0,
    ], $submissionAttrs));

    $submission->spklDocument()->create(array_merge([
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ], $spklAttrs));

    return $submission->fresh(['spklDocument', 'submittedBy', 'section', 'department']);
}

test('dispatch spkl reminders command dispatches job or runs synchronously', function () {
    Queue::fake();

    $this->artisan('overtime:spkl-reminders')
        ->expectsOutputToContain('Checking pending SPKL documents')
        ->expectsOutputToContain('dispatched to queue')
        ->assertSuccessful();

    Queue::assertPushed(SendSpklReminderJob::class);

    $this->artisan('overtime:spkl-reminders --sync')
        ->expectsOutputToContain('Checking pending SPKL documents')
        ->expectsOutputToContain('completed synchronously')
        ->assertSuccessful();
});

test('overtime:spkl-reminders is registered in console schedule daily at 08:00 WIB', function () {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events())->filter(function ($event) {
        return str_contains((string) $event->command, 'overtime:spkl-reminders');
    });

    expect($events->isNotEmpty())->toBeTrue();

    $event = $events->first();
    expect($event->expression)->toBe('0 8 * * *')
        ->and($event->timezone)->toBe('Asia/Jakarta');
});

test('send spkl reminder job notifies team leader for overdue spkl documents', function () {
    Notification::fake();

    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(3)->toDateString(),
    ]);

    $job = new SendSpklReminderJob;
    $job->handle();

    Notification::assertSentTo(
        $submission->submittedBy,
        SpklOverdueNotification::class,
        function (SpklOverdueNotification $notification) use ($submission) {
            $data = $notification->toArray($submission->submittedBy);

            return $notification->submission->id === $submission->id
                && $data['overdue_days'] === 3
                && $data['reminder_type'] === 'overdue'
                && $data['submission_code'] === $submission->submission_code;
        }
    );
});

test('send spkl reminder job notifies team leader for pre-due spkl documents due tomorrow', function () {
    Notification::fake();

    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->addDay()->toDateString(),
    ]);

    $job = new SendSpklReminderJob;
    $job->handle();

    Notification::assertSentTo(
        $submission->submittedBy,
        SpklPreDueNotification::class,
        function (SpklPreDueNotification $notification) use ($submission) {
            $data = $notification->toArray($submission->submittedBy);

            return $notification->submission->id === $submission->id
                && $data['reminder_type'] === 'predue'
                && $data['submission_code'] === $submission->submission_code;
        }
    );
});

test('send spkl reminder job skips attached or verified spkl documents', function () {
    Notification::fake();

    createReminderTestSubmission([], [
        'status' => 'ATTACHED',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    createReminderTestSubmission([], [
        'status' => 'VERIFIED',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $job = new SendSpklReminderJob;
    $job->handle();

    Notification::assertNothingSent();
});

test('send spkl reminder job respects user preference when disabled', function () {
    Notification::fake();

    $tl = User::factory()->create([
        'preferences' => [
            'spkl_pending_reminder' => false,
        ],
    ]);

    createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ], $tl);

    $job = new SendSpklReminderJob;
    $job->handle();

    Notification::assertNothingSent();
});

test('send spkl reminder job is idempotent and does not send duplicate notifications on the same day', function () {
    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $user = $submission->submittedBy;

    // Run 1: Should create 1 notification
    $job = new SendSpklReminderJob;
    $job->handle();

    expect($user->notifications()->count())->toBe(1);

    // Run 2: Should not create another duplicate notification
    $job2 = new SendSpklReminderJob;
    $job2->handle();

    expect($user->notifications()->count())->toBe(1);
});

test('targeted send spkl reminder job only processes specified submission', function () {
    Notification::fake();

    $submission1 = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $submission2 = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $job = new SendSpklReminderJob(submissionId: $submission1->id);
    $job->handle();

    Notification::assertSentTo(
        $submission1->submittedBy,
        SpklOverdueNotification::class,
        fn ($notif) => $notif->submission->id === $submission1->id
    );

    Notification::assertNotSentTo(
        $submission2->submittedBy,
        SpklOverdueNotification::class
    );
});

test('unauthenticated user cannot access notifications api', function () {
    $this->getJson('/notifications')->assertUnauthorized();
    $this->patchJson('/notifications/some-id/read')->assertUnauthorized();
    $this->patchJson('/notifications/read-all')->assertUnauthorized();
});

test('authenticated user can fetch their unread notifications', function () {
    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $user = $submission->submittedBy;
    $user->notify(new SpklOverdueNotification($submission, $submission->spklDocument, 2));

    $response = $this->actingAs($user)->getJson('/notifications');

    $response->assertOk()
        ->assertJsonStructure([
            'unread_count',
            'notifications' => [
                '*' => [
                    'id',
                    'type',
                    'data' => [
                        'submission_id',
                        'submission_code',
                        'section_name',
                        'due_date',
                        'overdue_days',
                        'reminder_type',
                        'title',
                        'message',
                    ],
                    'read_at',
                    'created_at',
                ],
            ],
        ]);

    expect($response->json('unread_count'))->toBe(1)
        ->and($response->json('notifications.0.data.submission_code'))->toBe($submission->submission_code);
});

test('user cannot see another user notifications', function () {
    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $user1 = $submission->submittedBy;
    $user1->notify(new SpklOverdueNotification($submission, $submission->spklDocument, 2));

    $user2 = User::factory()->create();

    $response = $this->actingAs($user2)->getJson('/notifications');

    $response->assertOk();
    expect($response->json('unread_count'))->toBe(0)
        ->and($response->json('notifications'))->toBeEmpty();
});

test('authenticated user can mark a specific notification as read', function () {
    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $user = $submission->submittedBy;
    $user->notify(new SpklOverdueNotification($submission, $submission->spklDocument, 2));

    $notification = $user->unreadNotifications()->first();
    expect($notification)->not->toBeNull();

    $response = $this->actingAs($user)->patchJson("/notifications/{$notification->id}/read");

    $response->assertOk()
        ->assertJson([
            'message' => 'Notification marked as read.',
            'unread_count' => 0,
        ]);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('user cannot mark another user notification as read', function () {
    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);

    $user1 = $submission->submittedBy;
    $user1->notify(new SpklOverdueNotification($submission, $submission->spklDocument, 2));
    $notification = $user1->unreadNotifications()->first();

    $user2 = User::factory()->create();

    $response = $this->actingAs($user2)->patchJson("/notifications/{$notification->id}/read");

    $response->assertNotFound();
    expect($user1->fresh()->unreadNotifications()->count())->toBe(1);
});

test('authenticated user can mark all notifications as read', function () {
    $submission1 = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(2)->toDateString(),
    ]);
    $user = $submission1->submittedBy;

    $submission2 = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(1)->toDateString(),
    ], $user);

    $user->notify(new SpklOverdueNotification($submission1, $submission1->spklDocument, 2));
    $user->notify(new SpklOverdueNotification($submission2, $submission2->spklDocument, 1));

    expect($user->unreadNotifications()->count())->toBe(2);

    $response = $this->actingAs($user)->patchJson('/notifications/read-all');

    $response->assertOk()
        ->assertJson([
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('overdue reminders do not block team leader from submitting new overtime (BR-05 non-blocking)', function () {
    $submission = createReminderTestSubmission([], [
        'status' => 'PENDING',
        'due_date' => Carbon::now('Asia/Jakarta')->subDays(5)->toDateString(),
    ]);

    $user = $submission->submittedBy;
    $user->notify(new SpklOverdueNotification($submission, $submission->spklDocument, 5));

    // Team Leader with overdue SPKL can still access create timesheet form and submit
    $response = $this->actingAs($user)->get(route('overtime.submissions.create'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('overtime/Create')
        ->where('unread_notifications_count', 1)
    );
});
