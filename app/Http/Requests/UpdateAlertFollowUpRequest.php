<?php

namespace App\Http\Requests;

class UpdateAlertFollowUpRequest extends StoreAlertFollowUpRequest
{
    /**
     * Only draft follow-ups of the user's scope can be edited.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('alertFollowUp')) ?? false;
    }
}
