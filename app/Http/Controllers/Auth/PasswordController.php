<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Change password for the signed-in user. Single user, no e-mail on this host,
 * so there is no "forgot password" flow — only this in-app change.
 */
class PasswordController extends Controller
{
    /** Minimum length for a new password. */
    public const MIN_LENGTH = 10;

    public function edit(): View
    {
        return view('auth.password', ['minLength' => self::MIN_LENGTH]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::min(self::MIN_LENGTH),
            ],
        ], [
            'current_password.current_password' => __('hub.password.incorrect'),
            'password.different' => __('hub.password.same'),
        ]);

        $user = $request->user();

        // A new remember token kills "remember me" cookies left on other
        // devices; the browser doing the change gets a fresh one below.
        $wasRemembered = $request->hasCookie(Auth::getRecallerName());

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Re-issues the session (new id) and, when it was in use, the
        // remember-me cookie matching the new token.
        Auth::login($user, $wasRemembered);

        return redirect()
            ->route('password.edit')
            ->with('status', __('hub.password.updated'));
    }
}
