<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the form to request a password reset link.
     */
    public function create(): View
    {
        return view('auth.passwords.email');
    }

    /**
     * Handle an incoming password reset request.
     *
     * Always comes back to the login screen with the reset dialog reopened,
     * carrying either the confirmation status or the validation errors.
     */
    public function store(Request $request): RedirectResponse
    {
        // The dialog's field is deliberately not named `email`, so a failure
        // here can never paint its error under the login form's own email field.
        $validator = Validator::make($request->all(), [
            'reset_email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->with('reset_modal', true)
                ->withErrors($validator->errors());
        }

        // We will not say whether the address exists — the same status is
        // returned either way so the form cannot be used to test addresses.
        Password::sendResetLink(['email' => $request->input('reset_email')]);

        return back()->with([
            'status' => trans('passwords.sent'),
            'reset_modal' => true,
        ]);
    }
}
