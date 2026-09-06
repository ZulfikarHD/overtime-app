<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployeeRequest;
use App\Http\Requests\Admin\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;

class EmployeeController extends Controller
{
    public function __construct(
        public EmployeeService $employeeService,
    ) {}

    /**
     * Store a newly created employee record.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->employeeService->create($request->validated());

        return redirect()->back()->with('success', __('Employee created successfully.'));
    }

    /**
     * Update the specified employee record.
     * Note: NPK is immutable and not updatable (BR-03).
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->employeeService->update($employee, $request->validated());

        return redirect()->back()->with('success', __('Employee updated successfully.'));
    }

    /**
     * Remove the specified employee record.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $this->employeeService->delete($employee);

        return redirect()->back()->with('success', __('Employee deleted successfully.'));
    }
}
