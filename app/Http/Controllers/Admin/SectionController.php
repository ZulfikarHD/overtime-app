<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSectionRequest;
use App\Http\Requests\Admin\UpdateSectionRequest;
use App\Models\Section;
use App\Services\SectionService;
use Illuminate\Http\RedirectResponse;

class SectionController extends Controller
{
    public function __construct(
        public SectionService $sectionService,
    ) {}

    /**
     * Store a newly created section under a department.
     */
    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $this->sectionService->create($request->validated());

        return redirect()->back()->with('success', __('Section created successfully.'));
    }

    /**
     * Update the specified section.
     */
    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $this->sectionService->update($section, $request->validated());

        return redirect()->back()->with('success', __('Section updated successfully.'));
    }

    /**
     * Remove the specified section.
     */
    public function destroy(Section $section): RedirectResponse
    {
        $this->sectionService->delete($section);

        return redirect()->back()->with('success', __('Section deleted successfully.'));
    }
}
