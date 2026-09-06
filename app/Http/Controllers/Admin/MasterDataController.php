<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    /**
     * Display the Master Data Hub.
     */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab', 'departments');

        $departments = Department::query()
            ->withCount([
                'employees',
                'sections',
                'overtimeSubmissions',
            ])
            ->with([
                'sections' => function ($query) {
                    $query->withCount([
                        'employees',
                        'overtimeSubmissions',
                    ])->orderBy('name');
                },
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/MasterData', [
            'activeTab' => $tab,
            'departments' => $departments,
        ]);
    }
}
