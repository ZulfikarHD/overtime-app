<?php

namespace App\Jobs;

use App\Models\SpklDocument;
use App\Notifications\SpklOverdueNotification;
use App\Notifications\SpklPreDueNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SendSpklReminderJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $submissionId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $nowWib = Carbon::now('Asia/Jakarta')->startOfDay();
        $todayStr = $nowWib->toDateString();
        $tomorrowStr = $nowWib->copy()->addDay()->toDateString();

        $baseQuery = SpklDocument::query()
            ->where('status', 'PENDING')
            ->with(['overtimeSubmission.submittedBy', 'overtimeSubmission.section']);

        if ($this->submissionId !== null) {
            $baseQuery->where('overtime_submission_id', $this->submissionId);
        }

        // 1. Process Overdue SPKL Documents (due_date <= today)
        $overdueDocs = (clone $baseQuery)
            ->whereDate('due_date', '<=', $todayStr)
            ->get();

        foreach ($overdueDocs as $spkl) {
            $submission = $spkl->overtimeSubmission;
            if (! $submission || ! $submission->submittedBy) {
                continue;
            }

            $submitter = $submission->submittedBy;
            if (! $submitter->getEffectivePreferences()['spkl_pending_reminder']) {
                continue;
            }

            $dueDate = Carbon::parse($spkl->due_date, 'Asia/Jakarta')->startOfDay();
            $overdueDays = max(0, (int) $dueDate->diffInDays($nowWib, false));

            $alreadySent = $submitter->notifications()
                ->whereDate('created_at', $todayStr)
                ->where('data->submission_id', $submission->id)
                ->where('data->reminder_type', 'overdue')
                ->exists();

            if (! $alreadySent) {
                $submitter->notify(new SpklOverdueNotification($submission, $spkl, $overdueDays));
            }
        }

        // 2. Process Pre-Due SPKL Documents (due_date == tomorrow)
        $preDueDocs = (clone $baseQuery)
            ->whereDate('due_date', '=', $tomorrowStr)
            ->get();

        foreach ($preDueDocs as $spkl) {
            $submission = $spkl->overtimeSubmission;
            if (! $submission || ! $submission->submittedBy) {
                continue;
            }

            $submitter = $submission->submittedBy;
            if (! $submitter->getEffectivePreferences()['spkl_pending_reminder']) {
                continue;
            }

            $alreadySent = $submitter->notifications()
                ->whereDate('created_at', $todayStr)
                ->where('data->submission_id', $submission->id)
                ->where('data->reminder_type', 'predue')
                ->exists();

            if (! $alreadySent) {
                $submitter->notify(new SpklPreDueNotification($submission, $spkl));
            }
        }
    }
}
