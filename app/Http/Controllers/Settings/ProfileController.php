<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Deactivate the user's profile without deleting operational history.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            $user->forceFill([
                'active_branch_id' => null,
                'is_active' => false,
            ])->save();

            $user->branches()->detach();
            $user->syncRoles([]);
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
