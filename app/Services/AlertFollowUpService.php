<?php

namespace App\Services;

use App\Enums\AlertFollowUpStatus;
use App\Enums\BurnZone;
use App\Enums\UserRole;
use App\Mail\AlertFollowUpMail;
use App\Models\AlertFollowUp;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AlertFollowUpService
{
    public function __construct(
        public EarlyWarningService $earlyWarningService,
    ) {}

    /**
     * Follow-ups of one alert (section + date), newest first.
     *
     * Authorization is the caller's job (the alert detail route authorizes the section first).
     *
     * @return Collection<int, AlertFollowUp>
     */
    public function listForAlert(Section $section, CarbonInterface $date): Collection
    {
        return AlertFollowUp::query()
            ->with(['section:id,code,name', 'whoDepartment:id,code,name', 'creator:id,name', 'sender:id,name'])
            ->where('section_id', $section->id)
            ->whereDate('alert_date', $date->toDateString())
            ->latest('id')
            ->get();
    }

    /**
     * Create or update the draft of an alert; optionally send it by email right away.
     *
     * The alert (zone, index, streak) is always recomputed server-side from the daily index, so
     * client values are never trusted. A follow-up can only exist for a date that is an alert,
     * a section has at most one draft per alert date, and nothing is saved when sending fails.
     *
     * @param  array<string, mixed>  $data  What / Why / When / Where / Who / How / How much, plus `section_id` and `alert_date` on create
     *
     * @throws ValidationException
     */
    public function save(User $user, array $data, ?AlertFollowUp $followUp = null, bool $send = false): AlertFollowUp
    {
        if ($followUp?->isSent()) {
            throw ValidationException::withMessages([
                'status' => __('A sent follow-up can no longer be edited.'),
            ]);
        }

        [$section, $alertDate] = $this->resolveAlertTarget($followUp, $data);

        $alert = $this->earlyWarningService->alertDetail($user, $section, $alertDate)['alert'];

        if ($alert === null) {
            throw ValidationException::withMessages([
                'alert_date' => __('A follow-up can only be saved for a date that is an alert (Kuning or Merah).'),
            ]);
        }

        $recipients = collect();

        $followUp = DB::transaction(function () use ($user, $data, $followUp, $send, $section, $alertDate, $alert, &$recipients): AlertFollowUp {
            if ($followUp !== null) {
                $followUp = AlertFollowUp::query()->lockForUpdate()->findOrFail($followUp->id);

                if ($followUp->isSent()) {
                    throw ValidationException::withMessages([
                        'status' => __('A sent follow-up can no longer be edited.'),
                    ]);
                }
            } else {
                $this->assertNoOpenDraft($section, $alertDate);

                $followUp = new AlertFollowUp([
                    'created_by' => $user->id,
                    'department_id' => $section->department_id,
                    'section_id' => $section->id,
                    'alert_date' => $alertDate->toDateString(),
                    'fiscal_year' => $alertDate->year,
                    'fiscal_month' => $alertDate->month,
                    'generated_payload' => $this->suggestionFor($alert),
                    'generated_at' => Carbon::now('Asia/Jakarta'),
                ]);
            }

            $followUp->fill([
                'zone' => BurnZone::from($alert['zone']),
                'index_pct' => $alert['index_pct'],
                'streak_days' => $alert['streak_days'],
                'what_text' => $data['what_text'] ?? null,
                'why_text' => $data['why_text'] ?? null,
                'when_date' => $data['when_date'] ?? null,
                'where_location' => $data['where_location'] ?? null,
                'who_department_id' => $data['who_department_id'] ?? null,
                'how_text' => $data['how_text'] ?? null,
                'how_much_cost_idr' => (int) ($data['how_much_cost_idr'] ?? 0),
                'how_much_hours' => (float) ($data['how_much_hours'] ?? 0),
            ]);

            if ($send) {
                $recipients = $this->recipientsFor((int) $followUp->who_department_id);

                if ($recipients->isEmpty()) {
                    throw ValidationException::withMessages([
                        'who_department_id' => __('The chosen department has no active recipients to notify.'),
                    ]);
                }

                $followUp->status = AlertFollowUpStatus::Sent;
                $followUp->sent_by = $user->id;
                $followUp->sent_at = Carbon::now('Asia/Jakarta');
            } else {
                $followUp->status = AlertFollowUpStatus::Draft;
            }

            $followUp->save();

            return $followUp;
        });

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->queue(new AlertFollowUpMail($followUp));
        }

        return $followUp;
    }

    /**
     * The system's starting 5W2H for an alert, keyed like the form fields.
     *
     * Rule-based and deterministic: every number comes from the alert itself. Fields the data cannot
     * justify yet (Why, How, How much) stay empty instead of being guessed. This is the baseline the
     * form is prefilled with and that is stored as `generated_payload`; E10-08 replaces it with the
     * full cause-based generator without changing this shape.
     *
     * @param  array<string, mixed>  $alert  An alert as returned by EarlyWarningService (index, zone, streak, limits)
     * @return array{what_text: string, why_text: string, when_date: string, where_location: string, who_department_id: int, how_text: string, how_much_cost_idr: int, how_much_hours: float}
     */
    public function suggestionFor(array $alert): array
    {
        $zone = BurnZone::from($alert['zone']);
        $limit = $zone === BurnZone::Red ? (float) $alert['yellow_max_pct'] : (float) $alert['green_max_pct'];
        $over = max(0.0, (float) $alert['index_pct'] - $limit);
        $streak = (int) $alert['streak_days'];

        $what = __('Daily index :section :index% (:zone), :over% above the :limit% limit', [
            'section' => $alert['section_name'],
            'index' => $this->formatNumber((float) $alert['index_pct']),
            'zone' => __($zone->label()),
            'over' => $this->formatNumber($over),
            'limit' => $this->formatNumber($limit),
        ]);

        if ($streak > 0) {
            $what .= ', '.__(':days consecutive days', ['days' => $streak]);
        }

        return [
            'what_text' => $what.'.',
            'why_text' => '',
            'when_date' => (string) $alert['alert_date'],
            'where_location' => filled($alert['department_name'] ?? null)
                ? $alert['department_name'].' — '.$alert['section_name']
                : (string) $alert['section_name'],
            'who_department_id' => (int) $alert['department_id'],
            'how_text' => '',
            'how_much_cost_idr' => 0,
            'how_much_hours' => 0.0,
        ];
    }

    /**
     * All active admin / manager / team leader users of the "Who" department.
     *
     * @return Collection<int, User>
     */
    public function recipientsFor(int $departmentId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where('department_id', $departmentId)
            ->whereIn('role', [
                UserRole::Admin->value,
                UserRole::Manager->value,
                UserRole::TeamLeader->value,
            ])
            ->whereNotNull('email')
            ->orderBy('name')
            ->get();
    }

    /**
     * Shape a follow-up for the alert detail form.
     *
     * @return array<string, mixed>
     */
    public function toPayload(AlertFollowUp $followUp): array
    {
        $followUp->loadMissing(['section:id,code,name', 'whoDepartment:id,code,name', 'creator:id,name', 'sender:id,name']);

        return [
            'id' => $followUp->id,
            'department_id' => $followUp->department_id,
            'section_id' => $followUp->section_id,
            'section_name' => $followUp->section?->name,
            'alert_date' => $followUp->alert_date?->toDateString(),
            'fiscal_year' => $followUp->fiscal_year,
            'fiscal_month' => $followUp->fiscal_month,
            'zone' => $followUp->zone->value,
            'index_pct' => (float) $followUp->index_pct,
            'streak_days' => $followUp->streak_days,
            'what_text' => $followUp->what_text,
            'why_text' => $followUp->why_text,
            'when_date' => $followUp->when_date?->toDateString(),
            'where_location' => $followUp->where_location,
            'who_department_id' => $followUp->who_department_id,
            'who_department_name' => $followUp->whoDepartment?->name,
            'how_text' => $followUp->how_text,
            'how_much_cost_idr' => $followUp->how_much_cost_idr,
            'how_much_hours' => (float) $followUp->how_much_hours,
            'generated_payload' => $followUp->generated_payload,
            'status' => $followUp->status->value,
            'status_label' => $followUp->status->label(),
            'is_editable' => ! $followUp->isSent(),
            'sent_at' => $followUp->sent_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'sent_by_name' => $followUp->sender?->name,
            'created_at' => $followUp->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'created_by_name' => $followUp->creator->name,
        ];
    }

    /**
     * The alert a follow-up belongs to: its own section and date when updating, the request's when creating.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Section, 1: Carbon}
     *
     * @throws ValidationException
     */
    protected function resolveAlertTarget(?AlertFollowUp $followUp, array $data): array
    {
        if ($followUp !== null) {
            if ($followUp->section_id === null || $followUp->alert_date === null) {
                throw ValidationException::withMessages([
                    'alert_date' => __('This follow-up is not linked to an alert and can no longer be edited.'),
                ]);
            }

            return [
                Section::query()->findOrFail($followUp->section_id),
                Carbon::createFromFormat('Y-m-d', $followUp->alert_date->toDateString(), 'Asia/Jakarta')->startOfDay(),
            ];
        }

        return [
            Section::query()->findOrFail((int) $data['section_id']),
            Carbon::createFromFormat('Y-m-d', (string) $data['alert_date'], 'Asia/Jakarta')->startOfDay(),
        ];
    }

    /**
     * An alert holds at most one draft at a time; sent follow-ups pile up as history.
     *
     * @throws ValidationException
     */
    protected function assertNoOpenDraft(Section $section, CarbonInterface $alertDate): void
    {
        $hasDraft = AlertFollowUp::query()
            ->where('section_id', $section->id)
            ->whereDate('alert_date', $alertDate->toDateString())
            ->where('status', AlertFollowUpStatus::Draft->value)
            ->lockForUpdate()
            ->exists();

        if ($hasDraft) {
            throw ValidationException::withMessages([
                'status' => __('This alert already has a draft. Edit the draft instead of creating a new one.'),
            ]);
        }
    }

    protected function formatNumber(float $value): string
    {
        return app()->getLocale() === 'en'
            ? number_format($value, 1, '.', ',')
            : number_format($value, 1, ',', '.');
    }
}
