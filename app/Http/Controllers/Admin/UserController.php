<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        public UserService $userService,
    ) {}

    /**
     * Store a newly created user account.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated(), $request->user());

        return redirect()->back()->with('success', __('User account created successfully.'));
    }

    /**
     * Update the specified user account.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $request->validated(), $request->user());

        return redirect()->back()->with('success', __('User account updated successfully.'));
    }

    /**
     * Remove the specified user account.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->userService->delete($user, $request->user());

        return redirect()->back()->with('success', __('User account deleted successfully.'));
    }

    /**
     * Send password reset link to user.
     */
    public function sendResetLink(Request $request, User $user): RedirectResponse
    {
        $this->userService->sendResetLink($user, $request->user());

        return redirect()->back()->with('success', __('Password reset link sent to :email.', ['email' => $user->email]));
    }
}
