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

    /**
     * A follow-up never moves to another alert, so the alert identity is not accepted on update.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return collect(parent::rules())
            ->except(['section_id', 'alert_date'])
            ->all();
    }
}
