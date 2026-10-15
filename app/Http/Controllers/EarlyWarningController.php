<?php

namespace App\Http\Controllers;

use App\Http\Requests\EarlyWarningInboxRequest;
use App\Models\AlertFollowUp;
use App\Models\Department;
use App\Models\Section;
use App\Services\AlertFollowUpService;
use App\Services\EarlyWarningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class EarlyWarningController extends Controller
{
    /**
     * Session key holding the last inbox query string, used by the detail page's back link.
     */
    protected const INBOX_QUERY_SESSION_KEY = 'early_warning.inbox_query';

    public function __construct(
        public EarlyWarningService $earlyWarningService,
        public AlertFollowUpService $followUpService,
    ) {}

    /**
     * Alert inbox. Filters live in the query string; the (heavy) alerts and summary load as deferred props.
     */
    public function index(EarlyWarningInboxRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->filters();
        $scope = $this->earlyWarningService->inboxScope($user, $filters['department_id'], $filters['section_id']);
        $filters = [...$filters, 'department_id' => $scope['department_id'], 'section_id' => $scope['section_id']];

        $request->session()->put(self::INBOX_QUERY_SESSION_KEY, (string) $request->getQueryString());

        $inbox = null;
        $loadInbox = function () use (&$inbox, $user, $filters): array {
            return $inbox ??= $this->earlyWarningService->inboxFor($user, $filters);
        };

        return Inertia::render('early-warning/Index', [
            'filters' => $filters,
            'departments' => $scope['departments'],
            'sections' => $scope['sections'],
            'summary' => Inertia::defer(fn (): array => $loadInbox()['summary'], 'inbox'),
            'alerts' => Inertia::defer(fn (): array => $loadInbox()['alerts'], 'inbox'),
        ]);
    }

    /**
     * Alert detail of one section on one date: the alert (null when the date is not an alert),
     * the daily index series, the zone limits, the follow-ups already linked to the alert and the
     * system suggestion the 5W2H form opens with (null when there is no alert to follow up).
     *
     * An invalid date goes back to the inbox with an error; another department's section is a 403.
     */
    public function show(Request $request, Section $section, string $date): Response|RedirectResponse
    {
        Gate::authorize('viewAlertSection', [AlertFollowUp::class, $section]);

        $validator = Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']]);

        if ($validator->fails()) {
            return redirect()
                ->route('early-warning.index')
                ->with('error', __('The alert date is invalid. Use the format YYYY-MM-DD.'));
        }

        $alertDate = Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->startOfDay();
        $detail = $this->earlyWarningService->alertDetail($request->user(), $section, $alertDate);

        return Inertia::render('early-warning/Show', [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'department_id' => $section->department_id,
                'department_name' => $section->department?->name,
            ],
            'date' => $alertDate->toDateString(),
            'alert' => $detail['alert'],
            'series' => $detail['series'],
            'limits' => [
                'green_max_pct' => $detail['green_max_pct'],
                'yellow_max_pct' => $detail['yellow_max_pct'],
            ],
            'vitals' => $this->earlyWarningService->vitalsFor($section, $alertDate),
            'followUps' => $this->followUpService
                ->listForAlert($section, $alertDate)
                ->map(fn (AlertFollowUp $followUp): array => $this->followUpService->toPayload($followUp))
                ->values()
                ->all(),
            // A closure: skipped by partial reloads that do not ask for it (save / send), recomputed by `only: ['suggestion']`.
            'suggestion' => fn (): ?array => $detail['alert']
                ? $this->followUpService->suggestionFor($request->user(), $section, $alertDate)
                : null,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'backUrl' => $this->inboxUrl($request),
        ]);
    }

    /**
     * Inbox URL (with its filters) the user came from.
     *
     * The same-origin Referer wins; after a reload it falls back to the last inbox query kept in the
     * session. Only the inbox path plus a query string is ever returned, never a foreign URL.
     */
    protected function inboxUrl(Request $request): string
    {
        $inboxPath = route('early-warning.index', absolute: false);
        $referer = parse_url((string) $request->headers->get('referer'));

        $query = ($referer['host'] ?? null) === $request->getHost() && ($referer['path'] ?? null) === $inboxPath
            ? (string) ($referer['query'] ?? '')
            : (string) $request->session()->get(self::INBOX_QUERY_SESSION_KEY, '');

        return $query === '' ? $inboxPath : $inboxPath.'?'.$query;
    }
}
