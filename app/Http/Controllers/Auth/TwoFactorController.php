<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
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
     * user proves their authenticator reads it. Requires the current
     * password first, so a walked-away session alone cannot start 2FA.
     * The pending secret is bound to this user and expires after 15 minutes.
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

        request()->validate([
            'password' => ['required', 'string', 'max:72', 'current_password'],
        ], [
            'password.required' => 'Enter your current password to start two-factor enrollment.',
            'password.max' => 'Your current password must not be longer than 72 characters.',
            'password.current_password' => 'The password you entered is incorrect.',
        ]);

        session([
            'two_factor.pending_secret' => app(TotpService::class)->generateSecret(),
            'two_factor.enroll_user_id' => $user->id,
            'two_factor.enroll_started_at' => now()->timestamp,
        ]);

        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'Scan the setup details below with your authenticator app, then enter the 6-digit code to finish.');
    }

    /**
     * Finish enrollment by proving the authenticator produces valid codes.
     * Disable-first: an already-confirmed account cannot be re-provisioned
     * here — disabling (which requires a live factor) comes first, so a
     * confirm call can never silently overwrite live 2FA.
     */
    public function confirm(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->isOfficeUser(), 403);

        if ($user->hasTwoFactorEnabled()) {
            session()->forget([
                'two_factor.pending_secret',
                'two_factor.enroll_user_id',
                'two_factor.enroll_started_at',
            ]);

            return redirect()
                ->route('two-factor.settings')
                ->with('status', 'Two-factor authentication is already enabled on this account. Disable it first to re-enroll.');
        }

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

        $totp = app(TotpService::class);
        $secret = session('two_factor.pending_secret');

        // Pending secrets are bound to the initiating user and expire after
        // 15 minutes; anything else restarts enrollment instead of guessing.
        if (! $this->pendingEnrollmentIsFresh($user)) {
            session()->forget([
                'two_factor.pending_secret',
                'two_factor.enroll_user_id',
                'two_factor.enroll_started_at',
            ]);

            return back()->withErrors(['code' => 'Your enrollment session expired. Start enrollment again, then enter the current code.']);
        }

        // Strict charset: the decoder skips unknown characters, so a
        // tampered session secret must fail here, never verify by accident.
        $step = is_string($secret) && $totp::isValidSecret($secret)
            ? $totp->matchedStep($secret, (string) $request->input('code'))
            : null;

        // Reject reuse of an already-consumed time-step (TOTP replay).
        // Scoped to enrollment: the first login afterwards runs under the
        // challenge scope, so enabling 2FA never burns the code that login
        // needs seconds later in the same step.
        $replayed = $step !== null && $totp->stepAlreadyUsed($this->totpReplayScope($user, 'enroll'), $step);

        if ($step === null || $replayed) {
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

        $totp->markStepUsed($this->totpReplayScope($user, 'enroll'), $step);

        RateLimiter::clear($this->confirmThrottleKey($user));

        $plainCodes = $totp->generateRecoveryCodes();

        $user->forceFill([
            // The 'encrypted' cast encrypts the secret at rest.
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(
                fn (string $code) => Hash::make(TotpService::normalizeRecoveryCode($code)),
                $plainCodes,
            ),
        ])->save();

        session()->forget([
            'two_factor.pending_secret',
            'two_factor.enroll_user_id',
            'two_factor.enroll_started_at',
        ]);

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
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ], [
            'code.required' => 'Enter the code from your authenticator app.',
            'code.min' => 'The code must be at least 6 characters.',
            'code.max' => 'The code must not be longer than 64 characters.',
        ]);

        $user = User::find(session('two_factor.pending_user_id'));

        if (! $user || ! $user->isOfficeUser() || ! $user->hasTwoFactorEnabled()) {
            session()->forget(['two_factor.pending_user_id', 'two_factor.pending_remember']);

            return redirect()->route('login');
        }

        // Fail closed: the password passed minutes ago at most, but the
        // account may have been suspended or unapproved since. Re-verify
        // immediately before finalizing login; one generic message either way
        // so the response never reveals which state changed.
        if ($user->isSuspended() || ! $user->isApproved()) {
            session()->forget(['two_factor.pending_user_id', 'two_factor.pending_remember']);

            return redirect()->route('login')->withErrors([
                'email' => 'This account is no longer available. Please contact the barangay office.',
            ]);
        }

        $this->ensureChallengeIsNotRateLimited($user);

        $totp = app(TotpService::class);
        $input = (string) $request->input('code');

        $secret = (string) $user->two_factor_secret;
        $step = $totp::isValidSecret($secret) ? $totp->matchedStep($secret, $input) : null;

        // A matching but already-consumed step is a replay, not a pass.
        $passed = $step !== null && ! $totp->stepAlreadyUsed($this->totpReplayScope($user, 'challenge'), $step);
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

        if ($step !== null) {
            $totp->markStepUsed($this->totpReplayScope($user, 'challenge'), $step);
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
     * Disable 2FA after confirming the current password AND a live factor
     * (authenticator code or single-use recovery code). Destructive, so the
     * view also gates the button behind data-confirm.
     */
    public function destroy(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->isOfficeUser(), 403);

        $request = request();

        $request->validate([
            'password' => ['required', 'string', 'max:72', 'current_password', Password::min(12)->letters()->numbers()],
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ], [
            'password.required' => 'Enter your current password to disable two-factor authentication.',
            'password.max' => 'Your current password must not be longer than 72 characters.',
            'password.current_password' => 'The password you entered is incorrect.',
            'code.required' => 'Enter the 6-digit code from your authenticator app (or a recovery code) to disable two-factor authentication.',
            'code.min' => 'The code must be at least 6 characters.',
            'code.max' => 'The code must not be longer than 64 characters.',
        ]);

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('two-factor.settings')
                ->with('status', 'Two-factor authentication is not enabled on this account.');
        }

        $input = (string) $request->input('code');
        $secret = (string) $user->two_factor_secret;

        $passed = app(TotpService::class)::isValidSecret($secret)
            && app(TotpService::class)->verify($secret, $input);

        if (! $passed) {
            $passed = $this->matchingRecoveryHash($user, $input) !== null;
        }

        if (! $passed) {
            AuditLog::record(
                'two_factor.verification_failed',
                $user->id,
                $user->email,
                $request->ip(),
                $request->userAgent(),
                ['stage' => 'disable'],
            );

            return back()->withErrors(['code' => 'That code is incorrect. Check your authenticator app and try again.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        session()->forget([
            'two_factor.pending_secret',
            'two_factor.enroll_user_id',
            'two_factor.enroll_started_at',
        ]);

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

        // Capped before Hash::check: bcrypt inputs must stay small, and real
        // recovery codes normalize to 10 characters.
        if ($normalized === '' || strlen($normalized) > 64) {
            return null;
        }

        foreach ($user->remainingRecoveryCodeHashes() as $hash) {
            if (Hash::check($normalized, $hash)) {
                return $hash;
            }
        }

        return null;
    }

    /**
     * Replay scope for TOTP step consumption. Keyed by user id so one
     * account's codes never burn another's, and by stage so confirming an
     * enrollment never burns the code the first login needs seconds later
     * in the same step. (Codes are secret-bound, so cross-account replay is
     * already cryptographically impossible; this stops same-account races.)
     */
    private function totpReplayScope(User $user, string $stage): string
    {
        return 'two-factor-totp:'.$stage.':'.$user->getKey();
    }

    /**
     * A pending enrollment is usable only by the user who started it and
     * only within 15 minutes of minting.
     */
    private function pendingEnrollmentIsFresh(User $user): bool
    {
        $secret = session('two_factor.pending_secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        if ((int) session('two_factor.enroll_user_id') !== (int) $user->id) {
            return false;
        }

        $startedAt = session('two_factor.enroll_started_at');

        return is_int($startedAt) && $startedAt >= now()->subMinutes(15)->timestamp;
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

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        // ValidationException renders as a redirect-back with errors, exactly
        // like LoginRequest's throttle handling. (abort() with a redirect
        // response 500s instead.)
        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
