<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Household;
use App\Models\Purok;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login view.
     */
    public function create(): View
    {
        // Registration validates into the "register" error bag so its errors
        // never paint under the sign-in fields — reopen the dialog when that
        // bag has messages, otherwise the feedback would stay hidden.
        $errors = session('errors');
        $registerFailed = $errors instanceof ViewErrorBag
            && $errors->getBag('register')->any();

        return view('auth.login', [
            'openResetModal' => (bool) session('reset_modal'),
            'openRegisterModal' => (bool) session('register_modal') || $registerFailed,
            'puroks' => Purok::orderBy('code')->get(['id', 'code', 'name']),
            'households' => Household::orderBy('household_number')->get(['id', 'household_number', 'address']),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Log the authenticated user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
