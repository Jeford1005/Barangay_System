<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /**
     * Office account area: enrollment status, the pending provisioning
     * details, or the disable form. Residents never reach this — every
     * action aborts unless the signed-in account is an office role.
     */
    public function show(): View
    {
        $user = Auth::user();
        abort_unless($user->isOfficeUser(), 403);

        return view('account.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pendingSecret' => session('two_factor.pending_secret'),
            'provisioningUri' => session('two_factor.pending_secret')
                ? app(TotpService::class)->provisioningUri(
                    session('two_factor.pending_secret'),
                    (string) $user->email,
                    (string) config('app.name', 'Barangay Management System'),
                )
                : null,
            'recoveryCodes' => session('two_factor.recovery_codes_plain'),
        ]);
    }

    /**
     * Start enrollment: mint a secret and hold it in the session until the
     * user proves their authenticator reads it.
     */
    public function enroll(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->isOfficeUser(), 403);

        if ($user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('two-factor.settings')
                ->with('status', 'Two-factor authentication is already enabled on this account.');
        }

        session([
            'two_factor.pending_secret' => app(TotpService::class)->generateSecret(),
        ]);

        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'Scan the setup details below with your authenticator app, then enter the 6-digit code to finish.');
    }

    /**
     * Finish enrollment by proving the authenticator produces valid codes.
     */
    public function confirm(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->isOfficeUser(), 403);

        $request = request();

        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:6', 'regex:/^\d{6}$/'],
        ], [
            'code.required' => 'Enter the 6-digit code from your authenticator app.',
            'code.min' => 'The code must be 6 digits.',
            'code.max' => 'The code must be 6 digits.',
            'code.regex' => 'The code must be 6 digits.',
        ]);

        $this->ensureConfirmIsNotRateLimited($user);

        $secret = session('two_factor.pending_secret');

        $valid = is_string($secret) && $secret !== ''
            && app(TotpService::class)->verify($secret, (string) $request->input('code'));

        if (! $valid) {
            RateLimiter::hit($this->confirmThrottleKey($user), 300);

            AuditLog::record(
                'two_factor.verification_failed',
                $user->id,
                $user->email,
                $request->ip(),
                $request->userAgent(),
                ['stage' => 'enroll_confirm'],
            );

            return back()->withErrors(['code' => 'That code is incorrect. Check your authenticator app and try again.']);
        }

        RateLimiter::clear($this->confirmThrottleKey($user));

        $plainCodes = app(TotpService::class)->generateRecoveryCodes();

        $user->forceFill([
            // The 'encrypted' cast encrypts the secret at rest.
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(
                fn (string $code) => Hash::make(TotpService::normalizeRecoveryCode($code)),
                $plainCodes,
            ),
        ])->save();

        session()->forget('two_factor.pending_secret');

        AuditLog::record(
            'two_factor.enrolled',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
        );

        // Plaintext codes travel in the flash session for this one render
        // only — they are never persisted or logged.
        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'Two-factor authentication is enabled. Save these recovery codes somewhere safe — each works once and they will not be shown again.')
            ->with('two_factor.recovery_codes_plain', $plainCodes);
    }

    /**
     * The second login step: shown after the password passes for office
     * accounts with confirmed TOTP.
     */
    public function challenge(): View|RedirectResponse
    {
        if (! session()->has('two_factor.pending_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verify the challenge code (authenticator code or single-use recovery
     * code) and complete the login the password step started.
     */
    public function verify(): RedirectResponse
    {
        $request = request();

        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:12'],
        ], [
            'code.required' => 'Enter the code from your authenticator app.',
            'code.min' => 'The code must be at least 6 characters.',
            'code.max' => 'The code must not be longer than 12 characters.',
        ]);

        $user = User::find(session('two_factor.pending_user_id'));

        if (! $user || ! $user->isOfficeUser() || ! $user->hasTwoFactorEnabled()) {
            session()->forget('two_factor.pending_user_id');

            return redirect()->route('login');
        }

        $this->ensureChallengeIsNotRateLimited($user);

        $totp = app(TotpService::class);
        $input = (string) $request->input('code');

        $passed = $totp->verify((string) $user->two_factor_secret, $input);
        $usedRecoveryHash = null;

        if (! $passed) {
            $usedRecoveryHash = $this->matchingRecoveryHash($user, $input);
            $passed = $usedRecoveryHash !== null;
        }

        if (! $passed) {
            RateLimiter::hit($this->challengeThrottleKey($user), 300);

            AuditLog::record(
                'two_factor.verification_failed',
                $user->id,
                $user->email,
                $request->ip(),
                $request->userAgent(),
                ['stage' => 'login_challenge'],
            );

            // Generic on purpose: the visitor already passed the password, so
            // this says nothing about whether the account exists.
            return back()->withErrors(['code' => 'That code is incorrect. Try again.']);
        }

        if ($usedRecoveryHash !== null) {
            // Single use: burn the code the moment it works.
            $user->forceFill([
                'two_factor_recovery_codes' => array_values(array_filter(
                    $user->remainingRecoveryCodeHashes(),
                    fn (string $hash) => ! hash_equals($hash, $usedRecoveryHash),
                )),
            ])->save();
        }

        RateLimiter::clear($this->challengeThrottleKey($user));
        session()->forget('two_factor.pending_user_id');

        Auth::login($user, (bool) session('two_factor.pending_remember'));
        session()->forget('two_factor.pending_remember');
        $request->session()->regenerate();

        AuditLog::record(
            'two_factor.verified',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
            $usedRecoveryHash !== null ? ['via' => 'recovery_code'] : ['via' => 'authenticator_code'],
        );

        AuditLog::record(
            'auth.login',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
            ['user_type' => $user->user_type],
        );

        $default = $user->user_type === 'resident'
            ? route('resident.portal')
            : route('dashboard');

        return redirect()->intended($default);
    }

    /**
     * Disable 2FA after confirming the current password. Destructive, so the
     * view also gates the button behind data-confirm.
     */
    public function destroy(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->isOfficeUser(), 403);

        $request = request();

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72', 'current_password'],
        ], [
            'password.required' => 'Enter your current password to disable two-factor authentication.',
            'password.min' => 'Your current password must be at least 8 characters.',
            'password.max' => 'Your current password must not be longer than 72 characters.',
            'password.current_password' => 'The password you entered is incorrect.',
        ]);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        session()->forget('two_factor.pending_secret');

        AuditLog::record(
            'two_factor.disabled',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'Two-factor authentication has been disabled.');
    }

    private function matchingRecoveryHash(User $user, string $input): ?string
    {
        $normalized = TotpService::normalizeRecoveryCode($input);

        if ($normalized === '') {
            return null;
        }

        foreach ($user->remainingRecoveryCodeHashes() as $hash) {
            if (Hash::check($normalized, $hash)) {
                return $hash;
            }
        }

        return null;
    }

    private function confirmThrottleKey(User $user): string
    {
        return Str::transliterate('two-factor-confirm:'.Str::lower((string) $user->email).'|'.request()->ip());
    }

    private function challengeThrottleKey(User $user): string
    {
        return Str::transliterate('two-factor-challenge:'.Str::lower((string) $user->email).'|'.request()->ip());
    }

    private function ensureConfirmIsNotRateLimited(User $user): void
    {
        $this->ensureIsNotRateLimited($this->confirmThrottleKey($user));
    }

    private function ensureChallengeIsNotRateLimited(User $user): void
    {
        $this->ensureIsNotRateLimited($this->challengeThrottleKey($user));
    }

    private function ensureIsNotRateLimited(string $key): void
    {
        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        abort(redirect()->back()->withErrors([
            'code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]));
    }
}
