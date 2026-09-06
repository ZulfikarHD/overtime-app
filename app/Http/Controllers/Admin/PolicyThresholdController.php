<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePolicyThresholdRequest;
use App\Http\Requests\Admin\UpdatePolicyThresholdRequest;
use App\Models\PolicyThreshold;
use App\Services\PolicyThresholdService;
use Illuminate\Http\RedirectResponse;

class PolicyThresholdController extends Controller
{
    public function __construct(
        public PolicyThresholdService $policyService,
    ) {}

    /**
     * Store or create a policy threshold (plant default or department override).
     */
    public function store(StorePolicyThresholdRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['department_id'])) {
            $this->policyService->updatePlantDefault($data);
            $message = __('Plant-wide default policy threshold updated successfully.');
        } else {
            $this->policyService->saveOverride((int) $data['department_id'], $data);
            $message = __('Department policy override saved successfully.');
        }

        return redirect()
            ->route('admin.administration', ['tab' => 'policies'])
            ->with('success', $message);
    }

    /**
     * Update an existing policy threshold.
     */
    public function update(UpdatePolicyThresholdRequest $request, PolicyThreshold $policyThreshold): RedirectResponse
    {
        $data = $request->validated();

        if ($policyThreshold->department_id === null) {
            $this->policyService->updatePlantDefault($data);
            $message = __('Plant-wide default policy threshold updated successfully.');
        } else {
            $this->policyService->saveOverride($policyThreshold->department_id, $data);
            $message = __('Department policy override updated successfully.');
        }

        return redirect()
            ->route('admin.administration', ['tab' => 'policies'])
            ->with('success', $message);
    }

    /**
     * Delete a department override so it inherits plant-wide default.
     */
    public function destroy(PolicyThreshold $policyThreshold): RedirectResponse
    {
        $this->policyService->deleteOverride($policyThreshold);

        return redirect()
            ->route('admin.administration', ['tab' => 'policies'])
            ->with('success', __('Department policy override deleted successfully. Department will inherit plant default.'));
    }
}
