<?php

use App\Jobs\RecalculateMonthlyBurnSnapshotJob;
use App\Jobs\RunAnomalyDetectionJob;
use App\Jobs\SendSpklReminderJob;
use Illuminate\Support\Facades\Queue;

test('run anomaly detection job has correct queue configuration and can be dispatched', function () {
    Queue::fake();

    $job = new RunAnomalyDetectionJob(overtimeItemId: 42);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120, 300])
        ->and($job->overtimeItemId)->toBe(42);

    RunAnomalyDetectionJob::dispatch(42);

    Queue::assertPushed(RunAnomalyDetectionJob::class, function ($pushedJob) {
        return $pushedJob->overtimeItemId === 42;
    });
});

test('recalculate monthly burn snapshot job has correct queue configuration and can be dispatched', function () {
    Queue::fake();

    $job = new RecalculateMonthlyBurnSnapshotJob(sectionId: 10, fiscalYear: 2026, fiscalMonth: 9);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120, 300])
        ->and($job->sectionId)->toBe(10)
        ->and($job->fiscalYear)->toBe(2026)
        ->and($job->fiscalMonth)->toBe(9);

    RecalculateMonthlyBurnSnapshotJob::dispatch(10, 2026, 9);

    Queue::assertPushed(RecalculateMonthlyBurnSnapshotJob::class, function ($pushedJob) {
        return $pushedJob->sectionId === 10
            && $pushedJob->fiscalYear === 2026
            && $pushedJob->fiscalMonth === 9;
    });
});

test('send spkl reminder job has correct queue configuration and can be dispatched', function () {
    Queue::fake();

    $job = new SendSpklReminderJob(submissionId: 101);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120, 300])
        ->and($job->submissionId)->toBe(101);

    SendSpklReminderJob::dispatch(101);

    Queue::assertPushed(SendSpklReminderJob::class, function ($pushedJob) {
        return $pushedJob->submissionId === 101;
    });
});

test('all scaffolded background jobs execute handle method cleanly without errors', function () {
    $anomalyJob = new RunAnomalyDetectionJob(1);
    $burnJob = new RecalculateMonthlyBurnSnapshotJob(1, 2026, 9);
    $spklJob = new SendSpklReminderJob(1);

    // Call handle directly to verify empty stub does not throw exceptions
    $anomalyJob->handle();
    $burnJob->handle();
    $spklJob->handle();

    expect(true)->toBeTrue();
});

test('redis queue connection is properly configured in config', function () {
    $redisConfig = config('queue.connections.redis');

    expect($redisConfig)->toBeArray()
        ->and($redisConfig['driver'])->toBe('redis')
        ->and($redisConfig['connection'])->toBe('default')
        ->and($redisConfig['queue'])->toBe('default');
});
