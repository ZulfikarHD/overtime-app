<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAlertCauseMappingRequest;
use App\Services\AlertCauseMappingService;
use Illuminate\Http\RedirectResponse;

class AlertCauseMappingController extends Controller
{
    public function __construct(
        public AlertCauseMappingService $mappingService,
    ) {}

    /**
     * Save the cause → PIC department mapping used by the Early Warning follow-up generator.
     */
    public function update(StoreAlertCauseMappingRequest $request): RedirectResponse
    {
        $this->mappingService->save($request->validated('mappings'));

        return redirect()
            ->route('admin.administration', ['tab' => 'policies'])
            ->with('success', __('PIC per cause saved. It applies to new suggestions only.'));
    }
}
