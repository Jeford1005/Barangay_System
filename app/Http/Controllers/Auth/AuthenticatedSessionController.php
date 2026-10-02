<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Purok;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', [
            // Remaining resend cooldown, so the forgot-password dialog's
            // countdown is honest right after a page reload.
            'resetCooldown' => PasswordResetCodeController::cooldownRemaining(),

            // Dropdown options for the create-account dialog on the login page
            // (same lists the standalone registration form uses). The
            // household list is guest-visible, so it is capped at 100 rows
            // with no street detail — the field is optional and staff link
            // the household at approval, so an uncapped code+street list
            // would only serve registry harvesting.
            'puroks' => Cache::remember('auth.purok-options', now()->addMinutes(5), fn () => Purok::orderBy('name')->get(['id', 'name'])),
            'households' => Cache::remember('auth.household-options-capped', now()->addMinutes(5), fn () => Household::orderBy('household_code')->limit(100)->get(['id', 'household_code'])),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Never inherit a challenge marker from an earlier login in this
        // browser session (e.g. a resident signing in after an abandoned
        // office challenge).
        $request->session()->forget(['two_factor.pending_user_id', 'two_factor.pending_remember']);

        $user = Auth::user();

        // Office accounts with confirmed TOTP stop here for the second step:
        // the session is parked (logged out) with only a pending-user marker,
        // so auth.login is recorded only after the code verifies. Residents
        // are always password-only and never enter this branch.
        if ($user->isOfficeUser() && $user->hasTwoFactorEnabled()) {
            $request->session()->put('two_factor.pending_user_id', $user->id);
            $request->session()->put('two_factor.pending_remember', $request->boolean('remember'));

            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return redirect()->route('two-factor.challenge');
        }

        AuditLog::record(
            'auth.login',
            $user?->id,
            $user?->email,
            $request->ip(),
            $request->userAgent(),
            ['user_type' => $user?->user_type],
        );

        // Role-aware landing: residents go to their own portal; office users
        // (administrators and staff) go to the management dashboard.
        $default = $user->user_type === 'resident'
            ? route('resident.portal')
            : route('dashboard');

        return redirect()->intended($default);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Capture the identity first: after logout there is no user left to
        // attribute the event to.
        $userId = Auth::id();
        $userEmail = Auth::user()?->email;

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        AuditLog::record(
            'auth.logout',
            $userId,
            $userEmail,
            $request->ip(),
            $request->userAgent(),
        );

        return redirect('/');
    }
}
