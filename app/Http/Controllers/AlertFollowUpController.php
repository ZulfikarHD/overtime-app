<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlertFollowUpRequest;
use App\Http\Requests\UpdateAlertFollowUpRequest;
use App\Models\AlertFollowUp;
use App\Models\User;
use App\Services\AlertFollowUpService;
use Illuminate\Http\RedirectResponse;

class AlertFollowUpController extends Controller
{
    public function __construct(
        public AlertFollowUpService $alertFollowUpService,
    ) {}

    /**
     * Save a new 5W2H follow-up as draft, or save and send it by email.
     */
    public function store(StoreAlertFollowUpRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $send = $request->boolean('send');

        $this->alertFollowUpService->save($user, $request->validated(), null, $send);

        return redirect()->back()->with('success', $this->successMessage($send));
    }

    /**
     * Update a draft follow-up, optionally sending it by email.
     */
    public function update(UpdateAlertFollowUpRequest $request, AlertFollowUp $alertFollowUp): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $send = $request->boolean('send');

        $this->alertFollowUpService->save($user, $request->validated(), $alertFollowUp, $send);

        return redirect()->back()->with('success', $this->successMessage($send));
    }

    protected function successMessage(bool $send): string
    {
        return $send
            ? __('Follow-up sent by email to the PIC department.')
            : __('Follow-up draft saved.');
    }
}
