<?php

namespace App\Http\Controllers;

use App\Models\AlertFollowUp;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class EarlyWarningController extends Controller
{
    /**
     * Alert inbox (content arrives with E10-03).
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', AlertFollowUp::class);

        return Inertia::render('early-warning/Index');
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
