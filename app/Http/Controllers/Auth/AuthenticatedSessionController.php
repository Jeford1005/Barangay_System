<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
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
            // (same lists the standalone registration form uses).
            'puroks' => Cache::remember('auth.purok-options', now()->addMinutes(5), fn () => Purok::orderBy('name')->get(['id', 'name'])),
            'households' => Cache::remember('auth.household-options', now()->addMinutes(5), fn () => Household::orderBy('household_code')->get(['id', 'household_code', 'street'])),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

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
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
