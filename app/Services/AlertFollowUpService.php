<?php

namespace App\Services;

use App\Enums\AlertFollowUpStatus;
use App\Enums\UserRole;
use App\Mail\AlertFollowUpMail;
use App\Models\AlertFollowUp;
use App\Models\Section;
use App\Models\User;
use App\Services\Analytics\DashboardKpiService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AlertFollowUpService
{
    public function __construct(
        public DashboardKpiService $kpiService,
    ) {}

    /**
     * Follow-ups of the exact dashboard scope and fiscal month, newest first.
     *
     * @return Collection<int, AlertFollowUp>
     */
    public function listForScope(User $user, ?string $date, ?int $departmentId, ?int $sectionId): Collection
    {
        [$scopedDepartmentId, $scopedSectionId] = $this->kpiService->resolveScoping(
            $user,
            $departmentId ?: null,
            $sectionId ?: null,
        );

        $period = $this->resolvePeriod($date);

        $query = AlertFollowUp::query()
            ->with(['section:id,code,name', 'whoDepartment:id,code,name', 'creator:id,name', 'sender:id,name'])
            ->where('fiscal_year', $period->year)
            ->where('fiscal_month', $period->month);

        $scopedDepartmentId
            ? $query->where('department_id', $scopedDepartmentId)
            : $query->whereNull('department_id');

        $scopedSectionId
            ? $query->where('section_id', $scopedSectionId)
            : $query->whereNull('section_id');

        return $query->latest('id')->limit(10)->get();
    }

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
     * Create or update a draft; optionally send it by email right away.
     *
     * The alert snapshot (zone / index) is always recomputed server-side from
     * the Early Warning chart so it cannot be tampered with by the client.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $user, array $data, ?AlertFollowUp $followUp = null, bool $send = false): AlertFollowUp
    {
        if ($followUp?->isSent()) {
            throw ValidationException::withMessages([
                'status' => __('A sent follow-up can no longer be edited.'),
            ]);
        }

        $recipients = collect();

        $followUp = DB::transaction(function () use ($user, $data, $followUp, $send, &$recipients): AlertFollowUp {
            $followUp ??= new AlertFollowUp(['created_by' => $user->id]);

            if (! $followUp->exists) {
                $followUp->fill($this->resolveAlertSnapshot($user, $data));
            } else {
                $followUp->fill($this->refreshAlertValues($followUp, $user));
            }

            $followUp->fill([
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
     * Shape a follow-up for the dashboard panel.
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveAlertSnapshot(User $user, array $data): array
    {
        $trend = $this->kpiService->getDailyIndexTrend(
            $user,
            $data['date'] ?? null,
            ! empty($data['department_id']) ? (int) $data['department_id'] : null,
            ! empty($data['section_id']) ? (int) $data['section_id'] : null,
        );

        return [
            'department_id' => $trend['scope']['department_id'],
            'section_id' => $trend['scope']['section_id'],
            'fiscal_year' => $trend['fiscal_year'],
            'fiscal_month' => $trend['fiscal_month'],
            'zone' => $trend['average_zone'],
            'index_pct' => $trend['average_index'],
        ];
    }

    /**
     * Refresh zone / index of an existing draft with the latest chart values.
     *
     * @return array<string, mixed>
     */
    protected function refreshAlertValues(AlertFollowUp $followUp, User $user): array
    {
        $trend = $this->kpiService->getDailyIndexTrend(
            $user,
            Carbon::create($followUp->fiscal_year, $followUp->fiscal_month, 1, 0, 0, 0, 'Asia/Jakarta')->toDateString(),
            $followUp->department_id,
            $followUp->section_id,
        );

        return [
            'zone' => $trend['average_zone'],
            'index_pct' => $trend['average_index'],
        ];
    }

    protected function resolvePeriod(?string $date): Carbon
    {
        try {
            return $date ? Carbon::parse($date, 'Asia/Jakarta') : Carbon::now('Asia/Jakarta');
        } catch (\Throwable) {
            return Carbon::now('Asia/Jakarta');
        }
    }
}
