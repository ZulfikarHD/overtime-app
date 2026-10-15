<?php

namespace App\Services;

use App\Enums\AlertCause;
use App\Enums\BurnZone;
use App\Models\AlertCausePicMapping;
use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Writes the 5W2H follow-up of an Early Warning alert from the data around it (E10-08).
 *
 * Rule-based and explainable: no AI model and no external call. The generator reads the alert, detects the
 * likely causes with fixed thresholds (`config/early_warning.php`), ranks them and fills the 5W2H from per-cause
 * templates. The same input always gives the same output, and every cause sentence cites the number it comes from.
 *
 * It is a pure service (no HTTP / Inertia knowledge). Scope safety comes from resolving the alert through
 * {@see EarlyWarningService::alertContext()}, which refuses a section the user may not see.
 *
 * @phpstan-type Evidence array{cause: string, score: float, values: array<string, float|int|string|null>}
 * @phpstan-type Suggestion array{
 *     what: string,
 *     why: string,
 *     when_date: string,
 *     when_text: string,
 *     where: string,
 *     who_department_id: int,
 *     how: string,
 *     how_much_hours: float,
 *     how_much_cost_idr: int,
 *     cost_note: ?string,
 *     excess_hours_per_day: float,
 *     suggested_headcount: ?int,
 *     primary_cause: string,
 *     supporting_causes: list<string>,
 *     evidence: list<Evidence>
 * }
 */
class EarlyWarningFollowUpGenerator
{
    /**
     * Supporting causes shown in "Why" next to the primary one.
     */
    protected const SUPPORTING_CAUSES = 2;

    public function __construct(
        public EarlyWarningService $earlyWarningService,
    ) {}

    /**
     * The system-written 5W2H of the alert, or null when the date is not an alert (nothing to follow up).
     *
     * @return Suggestion|null
     *
     * @throws AuthorizationException when the user may not see the section
     */
    public function generate(User $user, Section $section, CarbonInterface $date): ?array
    {
        $context = $this->earlyWarningService->alertContext($user, $section, $date);

        if ($context === null) {
            return null;
        }

        $alert = $context['alert'];
        $window = $context['window'];
        $alertDate = Carbon::parse($alert['alert_date'], 'Asia/Jakarta')->startOfDay();
        $windowStart = Carbon::parse($window[0]['date'] ?? $alert['alert_date'], 'Asia/Jakarta')->startOfDay();
        $historyStart = $alertDate->copy()->subDays(max(1, (int) config('early_warning.history_days', 7)) - 1);
        $periodFrom = $windowStart->lt($historyStart) ? $windowStart : $historyStart;

        $facts = [
            'vitals' => $this->earlyWarningService->vitalsFor($section, $alertDate),
            'categories' => $this->earlyWarningService->categoryBreakdown($section, $periodFrom, $alertDate),
            'top' => $this->earlyWarningService->topContributors($section, $periodFrom, $alertDate, $this->topEmployees()),
            'holiday' => $this->earlyWarningService->holidayShare($section, $periodFrom, $alertDate),
            'plan' => $this->earlyWarningService->plannedVsActual($section, $periodFrom, $alertDate),
        ];

        $causes = $this->rankCauses($this->detectCauses($facts));
        $primary = $causes->first();
        $supporting = $causes->slice(1, self::SUPPORTING_CAUSES)->values();

        $excessPerDay = $this->excessHoursPerDay($window);
        $followUpDays = max(1, (int) config('early_warning.follow_up_days', 7));
        $suggestedHours = round($excessPerDay * $followUpDays, 1);
        $costPerHour = $this->earlyWarningService->costPerOvertimeHour($section, $alertDate);
        $suggestedCost = $costPerHour === null ? 0 : (int) round($suggestedHours * $costPerHour);
        $headcount = $primary['cause'] === AlertCause::ManpowerShortage
            ? max(1, (int) ceil($excessPerDay / max(1, (int) config('early_warning.shift_hours', 8))))
            : null;

        return [
            'what' => $this->whatText($alert),
            'why' => $this->whyText($causes->take(1 + self::SUPPORTING_CAUSES)),
            'when_date' => $alert['alert_date'],
            'when_text' => $this->whenText($windowStart, $alertDate, (int) $alert['streak_days']),
            'where' => filled($alert['department_name'] ?? null)
                ? $alert['department_name'].' — '.$alert['section_name']
                : (string) $alert['section_name'],
            'who_department_id' => $this->resolveWhoDepartment($section, $primary['cause']),
            'how' => $this->howText($primary, [
                'count' => $headcount,
                'days' => $followUpDays,
                'excess' => $excessPerDay,
                'hours' => $suggestedHours,
                'cost' => $suggestedCost,
            ]),
            'how_much_hours' => $suggestedHours,
            'how_much_cost_idr' => $suggestedCost,
            'cost_note' => $costPerHour === null && $suggestedHours > 0 ? __('Estimasi biaya belum tersedia') : null,
            'excess_hours_per_day' => $excessPerDay,
            'suggested_headcount' => $headcount,
            'primary_cause' => $primary['cause']->value,
            'supporting_causes' => $supporting->map(fn (array $cause): string => $cause['cause']->value)->all(),
            'evidence' => $causes
                ->map(fn (array $cause): array => [
                    'cause' => $cause['cause']->value,
                    'score' => $cause['score'],
                    'values' => $cause['values'],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Run every detector against the facts; a detector whose data is missing is skipped, never guessed.
     *
     * @param  array<string, mixed>  $facts
     * @return list<array{cause: AlertCause, score: float, values: array<string, float|int|string|null>}>
     */
    protected function detectCauses(array $facts): array
    {
        $thresholds = (array) config('early_warning.thresholds', []);
        $detected = [];

        $vitals = $facts['vitals'];
        $volume = $vitals['volume_change_pct'];
        $manpower = $vitals['manpower_change_pct'];

        if ($volume !== null && $manpower !== null
            && $volume >= (float) ($thresholds['volume_increase_pct'] ?? 10) && $manpower <= 0) {
            // Demand grew by `gap` points that manpower did not follow: the share of the load that gap represents.
            $gap = $volume - $manpower;

            $detected[] = [
                'cause' => AlertCause::ManpowerShortage,
                'score' => round($gap / (100 + $gap), 4),
                'values' => [
                    'volume_change_pct' => $volume,
                    'volume_period_label' => $vitals['volume_period_label'],
                    'manpower_change_pct' => $manpower,
                    'manpower_period_label' => $vitals['manpower_period_label'],
                ],
            ];
        }

        $categories = $facts['categories'];
        $total = $categories['total'];

        if ($total > 0) {
            $dominant = null;
            foreach (['production', 'tpm', 'project', 'others'] as $category) {
                if ($dominant === null || $categories[$category] > $categories[$dominant]) {
                    $dominant = $category;
                }
            }

            $share = round($categories[$dominant] / $total * 100, 1);

            if ($share >= (float) ($thresholds['category_dominant_pct'] ?? 50)) {
                $detected[] = [
                    'cause' => AlertCause::CategoryDominant,
                    'score' => round($share / 100, 4),
                    'values' => ['category' => $dominant, 'share_pct' => $share, 'hours' => $categories[$dominant], 'total_hours' => $total],
                ];
            }

            $top = $facts['top'];

            // With fewer contributors than the top-N, "top N" is simply everybody and says nothing.
            if (count($top) >= $this->topEmployees()) {
                $topHours = array_sum(array_column($top, 'hours'));
                $topShare = round($topHours / $total * 100, 1);

                if ($topShare >= (float) ($thresholds['employee_concentration_pct'] ?? 40)) {
                    $detected[] = [
                        'cause' => AlertCause::EmployeeConcentration,
                        'score' => round(min(1, $topShare / 100), 4),
                        'values' => [
                            'count' => count($top),
                            'npks' => implode(', ', array_column($top, 'npk')),
                            'share_pct' => $topShare,
                            'hours' => round($topHours, 2),
                        ],
                    ];
                }
            }
        }

        $holiday = $facts['holiday'];

        if ($holiday['share_pct'] !== null && $holiday['share_pct'] >= (float) ($thresholds['holiday_share_pct'] ?? 30)) {
            $detected[] = [
                'cause' => AlertCause::HolidayOvertime,
                'score' => round($holiday['share_pct'] / 100, 4),
                'values' => ['share_pct' => $holiday['share_pct'], 'hours' => $holiday['holiday_hours'], 'total_hours' => $holiday['total_hours']],
            ];
        }

        $plan = $facts['plan'];

        if ($plan['overrun_pct'] !== null && $plan['overrun_pct'] >= (float) ($thresholds['plan_overrun_pct'] ?? 20)) {
            $detected[] = [
                'cause' => AlertCause::UnplannedOvertime,
                'score' => round(($plan['actual_hours'] - $plan['planned_hours']) / $plan['actual_hours'], 4),
                'values' => ['planned_hours' => $plan['planned_hours'], 'actual_hours' => $plan['actual_hours'], 'overrun_pct' => $plan['overrun_pct']],
            ];
        }

        return $detected;
    }

    /**
     * Highest score first; equal scores keep the catalog order. Nothing detected gives the UNKNOWN cause.
     *
     * @param  list<array{cause: AlertCause, score: float, values: array<string, float|int|string|null>}>  $detected
     * @return Collection<int, array{cause: AlertCause, score: float, values: array<string, float|int|string|null>}>
     */
    protected function rankCauses(array $detected): Collection
    {
        if ($detected === []) {
            return collect([['cause' => AlertCause::Unknown, 'score' => 0.0, 'values' => []]]);
        }

        return collect($detected)
            ->sort(fn (array $a, array $b): int => [$b['score'], $a['cause']->catalogOrder()] <=> [$a['score'], $b['cause']->catalogOrder()])
            ->values();
    }

    /**
     * Average daily actual hours minus the average daily pacing target over the streak days, never below 0.
     *
     * @param  list<array{date: string, actual_hours: float, pacing_hours: float, is_working_day: bool}>  $window
     */
    protected function excessHoursPerDay(array $window): float
    {
        if ($window === []) {
            return 0.0;
        }

        $actual = array_sum(array_column($window, 'actual_hours')) / count($window);
        $pacing = array_sum(array_column($window, 'pacing_hours')) / count($window);

        return max(0.0, round($actual - $pacing, 2));
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    protected function whatText(array $alert): string
    {
        $zone = BurnZone::from($alert['zone']);
        $limit = $zone === BurnZone::Red ? (float) $alert['yellow_max_pct'] : (float) $alert['green_max_pct'];
        $over = max(0.0, (float) $alert['index_pct'] - $limit);
        $streak = (int) $alert['streak_days'];

        $what = __('Daily index :section :index% (:zone), :over% above the :limit% limit', [
            'section' => $alert['section_name'],
            'index' => $this->number((float) $alert['index_pct']),
            'zone' => __($zone->label()),
            'over' => $this->number($over),
            'limit' => $this->number($limit),
        ]);

        if ($streak > 0) {
            $what .= ', '.__(':days consecutive days', ['days' => $streak]);
        }

        return $what.'.';
    }

    /**
     * One sentence per cause (primary first), each citing the number that justifies it.
     *
     * @param  Collection<int, array{cause: AlertCause, score: float, values: array<string, float|int|string|null>}>  $causes
     */
    protected function whyText(Collection $causes): string
    {
        return $causes
            ->map(fn (array $cause): string => $this->whySentence($cause['cause'], $cause['values']))
            ->implode('; ').'.';
    }

    /**
     * @param  array<string, float|int|string|null>  $values
     */
    protected function whySentence(AlertCause $cause, array $values): string
    {
        return match ($cause) {
            AlertCause::ManpowerShortage => __('Production volume :volume (:volume_period) while manpower is :manpower (:manpower_period)', [
                'volume' => $this->signedPercent((float) $values['volume_change_pct']),
                'volume_period' => (string) $values['volume_period_label'],
                'manpower' => $this->signedPercent((float) $values['manpower_change_pct']),
                'manpower_period' => (string) $values['manpower_period_label'],
            ]),
            AlertCause::CategoryDominant => __('Category :category holds :share% of the overtime hours', [
                'category' => $this->categoryLabel((string) $values['category']),
                'share' => $this->number((float) $values['share_pct']),
            ]),
            AlertCause::EmployeeConcentration => __('The top :count employees (:npks) hold :share% of the overtime hours', [
                'count' => (int) $values['count'],
                'npks' => (string) $values['npks'],
                'share' => $this->number((float) $values['share_pct']),
            ]),
            AlertCause::HolidayOvertime => __(':share% of the overtime hours were worked on weekends and holidays', [
                'share' => $this->number((float) $values['share_pct']),
            ]),
            AlertCause::UnplannedOvertime => __('Actual overtime of :actual hours against :plan hours in the approved plan (+:over%)', [
                'actual' => $this->number((float) $values['actual_hours']),
                'plan' => $this->number((float) $values['planned_hours']),
                'over' => $this->number((float) $values['overrun_pct']),
            ]),
            AlertCause::Unknown => __('Cause not yet identified from the available data'),
        };
    }

    /**
     * Action of the primary cause with its computed numbers.
     *
     * @param  array{cause: AlertCause, score: float, values: array<string, float|int|string|null>}  $primary
     * @param  array{count: ?int, days: int, excess: float, hours: float, cost: int}  $numbers
     */
    protected function howText(array $primary, array $numbers): string
    {
        $values = $primary['values'];

        $action = match ($primary['cause']) {
            AlertCause::ManpowerShortage => __('Add :count people for :days working days to cover the excess of :hours hours per day (:shift hours per shift).', [
                'count' => (int) $numbers['count'],
                'days' => $numbers['days'],
                'hours' => $this->number($numbers['excess']),
                'shift' => (int) config('early_warning.shift_hours', 8),
            ]),
            AlertCause::CategoryDominant => __('Review the workload and scheduling of the :category category (:share% of the overtime hours).', [
                'category' => $this->categoryLabel((string) $values['category']),
                'share' => $this->number((float) $values['share_pct']),
            ]),
            AlertCause::EmployeeConcentration => __('Redistribute the work across more employees; the top :count employees hold :share% of the hours.', [
                'count' => (int) $values['count'],
                'share' => $this->number((float) $values['share_pct']),
            ]),
            AlertCause::HolidayOvertime => __('Review weekend and holiday scheduling (:share% of the hours fall on non-working days).', [
                'share' => $this->number((float) $values['share_pct']),
            ]),
            AlertCause::UnplannedOvertime => __('Align the next overtime plan with the real demand (plan :plan hours, actual :actual hours).', [
                'plan' => $this->number((float) $values['planned_hours']),
                'actual' => $this->number((float) $values['actual_hours']),
            ]),
            AlertCause::Unknown => __('Investigate the cause in this section.'),
        };

        // The estimate is only quoted when the daily excess gave hours and a cost basis exists.
        if ($numbers['hours'] > 0 && $numbers['cost'] > 0) {
            $action .= ' '.__('Estimated need: :hours overtime hours (:cost).', [
                'hours' => $this->number($numbers['hours']),
                'cost' => $this->rupiah($numbers['cost']),
            ]);
        }

        return $action;
    }

    /**
     * "24–26 Okt 2026 (3 consecutive working days)": the streak from its first day to the alert date.
     */
    protected function whenText(Carbon $start, Carbon $end, int $streakDays): string
    {
        $locale = app()->getLocale();
        $format = fn (Carbon $day, string $pattern): string => $day->copy()->locale($locale)->translatedFormat($pattern);

        $range = match (true) {
            $start->isSameDay($end) => $format($end, 'j M Y'),
            $start->format('Y-m') === $end->format('Y-m') => $format($start, 'j').'–'.$format($end, 'j M Y'),
            $start->year === $end->year => $format($start, 'j M').' – '.$format($end, 'j M Y'),
            default => $format($start, 'j M Y').' – '.$format($end, 'j M Y'),
        };

        return $range.' ('.__(':days consecutive working days', ['days' => max(1, $streakDays)]).')';
    }

    /**
     * PIC department of the primary cause: the admin mapping, else the configured default, else the alert's own
     * department. A department without active recipients is passed over when another candidate has some.
     */
    protected function resolveWhoDepartment(Section $section, AlertCause $cause): int
    {
        $own = (int) $section->department_id;
        $candidates = [];

        $mapping = AlertCausePicMapping::query()->where('cause', $cause->value)->first();

        if ($mapping === null) {
            $code = $cause->defaultPicKey();
            $picId = $code !== null ? Department::query()->where('code', $code)->value('id') : null;
        } else {
            $picId = $mapping->is_active ? $mapping->pic_department_id : null;
        }

        if ($picId !== null && Department::query()->whereKey($picId)->where('is_active', true)->exists()) {
            $candidates[] = (int) $picId;
        }

        $candidates[] = $own;

        foreach (array_unique($candidates) as $departmentId) {
            if (User::query()->alertRecipientsOf($departmentId)->exists()) {
                return $departmentId;
            }
        }

        return $candidates[0];
    }

    protected function topEmployees(): int
    {
        return max(1, (int) config('early_warning.top_employees', 3));
    }

    protected function categoryLabel(string $category): string
    {
        return match ($category) {
            'production' => __('Production'),
            'tpm' => __('TPM'),
            'project' => __('Project'),
            default => __('Others'),
        };
    }

    /**
     * Number with one decimal in the locale's separators (id: 1.234,5 / en: 1,234.5).
     */
    protected function number(float $value): string
    {
        return app()->getLocale() === 'en'
            ? number_format($value, 1, '.', ',')
            : number_format($value, 1, ',', '.');
    }

    /**
     * Rupiah in Indonesian notation, e.g. "Rp 7.500.000".
     */
    protected function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    protected function signedPercent(float $value): string
    {
        return ($value > 0 ? '+' : '').$this->number($value).'%';
    }
}
