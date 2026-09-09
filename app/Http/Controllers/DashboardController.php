<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the main operational dashboard or redirect line operators to their self-service dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isUser()) {
            return redirect()->route('my.dashboard');
        }

        return Inertia::render('Dashboard');
    }
}
