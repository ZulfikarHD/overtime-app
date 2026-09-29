<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShiftScheduleRequest;
use App\Http\Requests\Admin\UpdateShiftScheduleRequest;
use App\Models\ShiftSchedule;
use App\Services\ShiftScheduleService;
use Illuminate\Http\RedirectResponse;

class ShiftScheduleController extends Controller
{
    public function __construct(
        public ShiftScheduleService $shiftScheduleService,
    ) {}

    /**
     * Store a new shift schedule period.
     */
    public function store(StoreShiftScheduleRequest $request): RedirectResponse
    {
        $this->shiftScheduleService->create(
            $request->validated(),
            $request->user()?->id,
        );

        return redirect()
            ->route('admin.administration', ['tab' => 'shifts'])
            ->with('success', __('Shift schedule saved successfully.'));
    }

    /**
     * Update an existing shift schedule period.
     */
    public function update(UpdateShiftScheduleRequest $request, ShiftSchedule $shiftSchedule): RedirectResponse
    {
        $this->shiftScheduleService->update($shiftSchedule, $request->validated());

        return redirect()
            ->route('admin.administration', ['tab' => 'shifts'])
            ->with('success', __('Shift schedule updated successfully.'));
    }

    /**
     * Delete a shift schedule period.
     */
    public function destroy(ShiftSchedule $shiftSchedule): RedirectResponse
    {
        $this->shiftScheduleService->delete($shiftSchedule);

        return redirect()
            ->route('admin.administration', ['tab' => 'shifts'])
            ->with('success', __('Shift schedule deleted successfully.'));
    }
}
