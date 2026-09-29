<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // The form offers one tab for both office roles, so the value that
            // arrives is a group, not a stored role.
            'user_type' => ['required', 'in:office,resident'],
        ];
    }

    /**
     * The stored roles the submitted tab is allowed to reach.
     *
     * @return array<int, string>
     */
    private function candidateRoles(): array
    {
        return $this->input('user_type') === 'resident'
            ? ['resident']
            : ['admin', 'staff', 'official'];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');

        $authenticated = false;

        foreach ($this->candidateRoles() as $role) {
            // `user_type` is a credential, so a mismatch simply matches no row.
            // Only the role that is actually stored reaches the password check,
            // which keeps this as cheap as the single attempt it replaces.
            if (Auth::attempt($credentials + ['user_type' => $role], $this->boolean('remember'))) {
                $authenticated = true;

                break;
            }
        }

        if (! $authenticated) {
            // Five minutes, not the default 60 seconds: a one-minute window is
            // refreshed by every further attempt, so a patient attacker is
            // never actually locked out.
            //
            // Hit once per request, never once per candidate role: two hits for
            // one mistyped password would halve the budget.
            RateLimiter::hit($this->throttleKey(), 300);

            throw ValidationException::withMessages([
                'password' => 'Invalid email or password.',
            ]);
        }

        // Accounts must be approved and not suspended before they can use
        // the authenticated application.
        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Please contact the barangay office.',
            ]);
        }

        if (! $user->isApproved()) {
            Auth::guard('web')->logout();

            $message = $user->isPending()
                ? 'Your account is still awaiting approval from the barangay office. We will email you once it is approved.'
                : 'This account was not approved. Please contact the barangay office for assistance.';

            throw ValidationException::withMessages(['email' => $message]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'password' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
