<?php

namespace App\Http\Controllers;

use App\Http\Requests\EarlyWarningInboxRequest;
use App\Models\AlertFollowUp;
use App\Models\Section;
use App\Services\EarlyWarningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class EarlyWarningController extends Controller
{
    public function __construct(public EarlyWarningService $earlyWarningService) {}

    /**
     * Alert inbox. Filters live in the query string; the (heavy) alerts and summary load as deferred props.
     */
    public function index(EarlyWarningInboxRequest $request): Response
    {
        $user = $request->user();
        $filters = $request->filters();
        $scope = $this->earlyWarningService->inboxScope($user, $filters['department_id'], $filters['section_id']);
        $filters = [...$filters, 'department_id' => $scope['department_id'], 'section_id' => $scope['section_id']];

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
     * Alert detail of one section on one date (content arrives with E10-04).
     *
     * An invalid date goes back to the inbox with an error; another department's section is a 403.
     */
    public function show(Section $section, string $date): Response|RedirectResponse
    {
        Gate::authorize('viewAlertSection', [AlertFollowUp::class, $section]);

        $validator = Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']]);

        if ($validator->fails()) {
            return redirect()
                ->route('early-warning.index')
                ->with('error', __('The alert date is invalid. Use the format YYYY-MM-DD.'));
        }

        $section->loadMissing('department:id,name');

        return Inertia::render('early-warning/Show', [
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'department_id' => $section->department_id,
                'department_name' => $section->department?->name,
            ],
            'date' => Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->toDateString(),
        ]);
    }
}
