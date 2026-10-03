<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\TwoFactorLoginCodeNotification;
use App\Services\PasswordResetCodeService;
use App\Services\TotpService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /**
     * Lost-card email bridge: one-time login codes live in their OWN cache
     * namespace with their OWN limits — never in `password_reset_tokens`.
     * PasswordResetCodeService has no purpose/namespace support (one row per
     * email), so sharing it would let a reset code validate as a login code
     * and vice versa. Limits mirror the reset flow on purpose.
     */
    private const LOGIN_CODE_TTL_MINUTES = 15;

    /** Wrong login-code guesses per account before the code is withdrawn. */
    private const LOGIN_CODE_FAILURE_LIMIT = 5;

    /** Seconds the per-account login-code failure counter survives. */
    private const LOGIN_CODE_FAILURE_DECAY_SECONDS = 900;

    /** Seconds before another login code may be emailed to the same account. */
    private const LOGIN_CODE_RESEND_COOLDOWN_SECONDS = 60;

    /** Remaining login-card codes at or below this trigger a renewal warning. */
    private const LOW_RECOVERY_CODES_THRESHOLD = 2;
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
            'isPaper' => $user->isPaperTwoFactor(),
            'remainingCodes' => $user->remainingRecoveryCodeCount(),
            'lowCodes' => $user->hasTwoFactorEnabled()
                && $user->remainingRecoveryCodeCount() <= self::LOW_RECOVERY_CODES_THRESHOLD,
            'emailFallback' => (bool) $user->email_otp_fallback,
            'pendingSecret' => session('two_factor.pending_secret'),
            'provisioningUri' => session('two_factor.pending_secret')
                ? app(TotpService::class)->provisioningUri(
                    session('two_factor.pending_secret'),
                    (string) $user->email,
                    (string) config('app.name', 'Barangay Management System'),
                )
                : null,
            // Paper-first enrollment: the printed card is minted alongside
            // the app secret so both paths share one password-gated,
            // 15-minute enrollment window.
            'pendingPaperCodes' => session('two_factor.pending_recovery_plain'),
            'recoveryCodes' => session('two_factor.recovery_codes_plain'),
        ]);
    }

    /**
     * Start enrollment: password-gated, minting BOTH the app secret (opt-in
     * authenticator path) and the printed-card codes (default paper path).
     * Both live server-side in the session, bound to this user, expiring
     * after 15 minutes; whichever path completes first consumes the window
     * and discards the other. Plaintext card codes stay in the session until
     * confirmed or abandoned — they are never persisted or logged.
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
            'two_factor.pending_recovery_plain' => app(TotpService::class)->generateRecoveryCodes(),
            'two_factor.enroll_user_id' => $user->id,
            'two_factor.enroll_started_at' => now()->timestamp,
        ]);

        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'Your printable login card is ready below. Print it, confirm it is stored safely, or choose the authenticator-app option instead.');
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
                'two_factor.pending_recovery_plain',
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
                'two_factor.pending_recovery_plain',
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
            'two_factor.pending_recovery_plain',
            'two_factor.enroll_user_id',
            'two_factor.enroll_started_at',
        ]);

        AuditLog::record(
            'two_factor.enrolled',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
            ['mode' => 'app'],
        );

        // Plaintext codes travel in the flash session for this one render
        // only — they are never persisted or logged.
        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'Two-factor authentication is enabled. Save these recovery codes somewhere safe — each works once and they will not be shown again.')
            ->with('two_factor.recovery_codes_plain', $plainCodes);
    }

    /**
     * Finish PAPER enrollment: enable 2FA with no TOTP secret. The printed
     * card (minted by enroll() and shown large-print) becomes the second
     * factor — daily sign-in is password + the next unused card code.
     *
     * Requires the printed-card checkbox AND a staff witness name, both
     * recorded in the enroll audit. Disable-first like confirm(): an
     * already-confirmed account cannot be re-provisioned here.
     */
    public function paperConfirm(): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user->isOfficeUser(), 403);

        if ($user->hasTwoFactorEnabled()) {
            session()->forget([
                'two_factor.pending_secret',
                'two_factor.pending_recovery_plain',
                'two_factor.enroll_user_id',
                'two_factor.enroll_started_at',
            ]);

            return redirect()
                ->route('two-factor.settings')
                ->with('status', 'Two-factor authentication is already enabled on this account. Disable it first to re-enroll.');
        }

        $request = request();

        $request->validate([
            'printed' => ['accepted'],
            'witness' => ['required', 'string', 'min:2', 'max:100'],
        ], [
            'printed.accepted' => 'Confirm that the login card is printed and stored safely.',
            'witness.required' => 'Enter the name of the staff member who witnessed the card printing.',
            'witness.min' => 'The witness name must be at least 2 characters.',
            'witness.max' => 'The witness name must not be longer than 100 characters.',
        ]);

        $this->ensureConfirmIsNotRateLimited($user);

        $plainCodes = session('two_factor.pending_recovery_plain');

        // Same freshness binding as the app path: the card belongs to the
        // user who passed the password minutes ago, and only for 15 minutes.
        if (! $this->pendingEnrollmentIsFresh($user)
            || ! is_array($plainCodes)
            || count($plainCodes) !== TotpService::RECOVERY_CODE_COUNT) {
            session()->forget([
                'two_factor.pending_secret',
                'two_factor.pending_recovery_plain',
                'two_factor.enroll_user_id',
                'two_factor.enroll_started_at',
            ]);

            return back()->withErrors(['printed' => 'Your enrollment session expired. Start enrollment again to print a fresh login card.']);
        }

        RateLimiter::clear($this->confirmThrottleKey($user));

        $witness = trim((string) $request->input('witness'));

        $user->forceFill([
            // Paper-only mode: confirmed with NO secret. isPaperTwoFactor()
            // derives the mode from exactly this state.
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(
                fn (string $code) => Hash::make(TotpService::normalizeRecoveryCode($code)),
                $plainCodes,
            ),
        ])->save();

        session()->forget([
            'two_factor.pending_secret',
            'two_factor.pending_recovery_plain',
            'two_factor.enroll_user_id',
            'two_factor.enroll_started_at',
        ]);

        // The witness identifier + timestamp are accountability metadata, not
        // secrets: AuditLog redaction leaves them intact while any code-like
        // key would be redacted.
        AuditLog::record(
            'two_factor.enrolled',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
            [
                'mode' => 'paper',
                'witness' => $witness,
                'witnessed_at' => now()->toIso8601String(),
            ],
        );

        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'Printed-card sign-in is enabled. Use the next unused code from your login card at each sign-in — each code works once.');
    }

    /**
     * The second login step: shown after the password passes for office
     * accounts with confirmed 2FA (authenticator app OR printed card).
     * Paper-mode accounts are prompted for their next login-card code;
     * accounts flagged by an administrator receive a one-time email code
     * instead of any card/app prompt.
     */
    public function challenge(): View|RedirectResponse
    {
        if (! session()->has('two_factor.pending_user_id')) {
            return redirect()->route('login');
        }

        $user = User::find(session('two_factor.pending_user_id'));
        $isPaper = $user !== null && $user->isPaperTwoFactor();
        $emailMode = $user !== null
            && $user->isOfficeUser()
            && $user->hasTwoFactorEnabled()
            && (bool) $user->email_otp_fallback;
        $remaining = $user !== null ? $user->remainingRecoveryCodeCount() : 0;
        $emailSendFailed = false;

        if ($emailMode && $user->isActive()) {
            // One live email code at a time: refreshing the page never
            // re-sends while a code is outstanding, and a short cooldown
            // paces re-issues after expiry. Suspended/unapproved accounts
            // get no code — verify() re-checks and refuses them anyway.
            if ($this->liveLoginCode($user) === null
                && ! Cache::has($this->loginCodeCooldownKey($user))) {
                try {
                    $this->issueLoginCode($user);
                } catch (\Throwable $exception) {
                    report($exception);
                    $emailSendFailed = true;
                }
            }
        }

        return view('auth.two-factor-challenge', [
            'mode' => $emailMode ? 'email' : ($isPaper ? 'paper' : 'app'),
            'lowCodes' => $isPaper && ! $emailMode && $remaining <= self::LOW_RECOVERY_CODES_THRESHOLD,
            'remainingCodes' => $remaining,
            'emailSendFailed' => $emailSendFailed,
        ]);
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

        $input = (string) $request->input('code');

        // Lost-card bridge: flagged accounts verify ONLY against the emailed
        // login code — TOTP and card codes are not accepted here, and the
        // emailed code is never accepted on the unflagged path below.
        if ((bool) $user->email_otp_fallback) {
            return $this->verifyEmailBridge($user, $input);
        }

        $totp = app(TotpService::class);

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
            // this says nothing about whether the account exists. Paper-mode
            // wording points at the card without revealing how many codes
            // are left.
            return back()->withErrors(['code' => $user->isPaperTwoFactor()
                ? 'That code is incorrect. Enter the next unused code from your printed login card.'
                : 'That code is incorrect. Try again.']);
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
     * Verify the emailed login code for a flagged account and complete the
     * login the password step started. The ONLY accepted credential here is
     * a live code from the login-code store — reset-table codes, TOTP, and
     * card codes all fail the hash check by construction.
     */
    private function verifyEmailBridge(User $user, string $input): RedirectResponse
    {
        $request = request();

        if ($this->liveLoginCode($user) === null) {
            // No live code (expired, withdrawn after too many guesses, or
            // the challenge-time send failed): issue a fresh one now so the
            // visitor is never stranded in front of an unwinnable form.
            try {
                $this->issueLoginCode($user);
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors(['code' => 'The sign-in email could not be sent. Reload this page and try again.']);
            }

            return back()->withErrors(['code' => 'That code is incorrect or expired. A fresh sign-in code was sent to your email — enter it here.']);
        }

        if (! $this->checkLoginCode($user, $input)) {
            RateLimiter::hit($this->challengeThrottleKey($user), 300);

            AuditLog::record(
                'two_factor.verification_failed',
                $user->id,
                $user->email,
                $request->ip(),
                $request->userAgent(),
                ['stage' => 'login_challenge_email'],
            );

            return back()->withErrors(['code' => 'That code is incorrect. Check the code sent to your email and try again.']);
        }

        // Single use: burn the moment it works.
        $this->burnLoginCode($user);

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
            ['via' => 'email_code'],
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
     * Disable 2FA after confirming the current password AND a live factor.
     * Never password-only: the factor is an authenticator code, a single-use
     * login-card code, or — for accounts flagged by an administrator — the
     * emailed login code. A paper-mode account with no usable card codes
     * left and no flag cannot self-disable; the error message says so and
     * points at the staff-assisted reset (an administrator clears the 2FA
     * columns and the holder re-enrolls with a staff witness), which is
     * documented here because no self-service path may exist for that
     * state without weakening the live-factor rule.
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

        // Email bridge for flagged accounts (e.g. a paper-mode holder whose
        // card is lost, including when no usable card code is left): the
        // emailed login code is a live, single-use factor sent to the
        // account inbox, and the flag itself is per-user, admin-granted, and
        // audited. Password alone is never sufficient.
        if (! $passed && (bool) $user->email_otp_fallback) {
            if ($this->liveLoginCode($user) === null) {
                // No live code to check against: send one now (active
                // accounts only) so the next attempt can succeed.
                if ($user->isActive()) {
                    try {
                        $this->issueLoginCode($user);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }

                return back()->withErrors(['code' => 'A fresh sign-in code was sent to your email. Enter it here to finish disabling two-factor authentication.']);
            }

            if ($this->checkLoginCode($user, $input)) {
                $this->burnLoginCode($user);
                $passed = true;
            }
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

            // No usable factor remains for an unflagged paper account with an
            // empty card: say so plainly instead of implying another guess
            // could work. Recovery is staff-assisted (administrator clears
            // the 2FA columns, holder re-enrolls with a staff witness).
            $message = $user->isPaperTwoFactor()
                && $user->remainingRecoveryCodeCount() === 0
                && ! (bool) $user->email_otp_fallback
                ? 'No usable login-card codes remain on this account, so it cannot be disabled here. Ask an administrator for a staff-assisted two-factor reset.'
                : 'That code is incorrect. Check your authenticator app and try again.';

            return back()->withErrors(['code' => $message]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        session()->forget([
            'two_factor.pending_secret',
            'two_factor.pending_recovery_plain',
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
     * The still-valid emailed login code for this account, or null when none
     * is outstanding. Expired rows are withdrawn on sight so a stale hash can
     * never validate late.
     *
     * @return array{hash: string, sent_at: int}|null
     */
    private function liveLoginCode(User $user): ?array
    {
        $record = Cache::get($this->loginCodeCacheKey($user));

        if (! is_array($record)
            || ! isset($record['hash'], $record['sent_at'])
            || ! is_string($record['hash'])
            || ! is_int($record['sent_at'])) {
            return null;
        }

        if ($record['sent_at'] + (self::LOGIN_CODE_TTL_MINUTES * 60) < now()->timestamp) {
            Cache::forget($this->loginCodeCacheKey($user));

            return null;
        }

        return $record;
    }

    /**
     * Issue a fresh emailed login code, replacing any previous one so old
     * codes die instantly. Throws when the mail cannot be sent, in which
     * case nothing is stored and no cooldown starts (mirrors the reset
     * service: the TTL starts when the mail is actually handed over).
     */
    private function issueLoginCode(User $user): string
    {
        $code = $this->generateLoginCode();

        $user->notify(new TwoFactorLoginCodeNotification($code, self::LOGIN_CODE_TTL_MINUTES));

        Cache::put(
            $this->loginCodeCacheKey($user),
            ['hash' => Hash::make(strtoupper($code)), 'sent_at' => now()->timestamp],
            now()->addMinutes(self::LOGIN_CODE_TTL_MINUTES),
        );
        Cache::put(
            $this->loginCodeCooldownKey($user),
            now()->timestamp,
            self::LOGIN_CODE_RESEND_COOLDOWN_SECONDS,
        );

        AuditLog::record(
            'two_factor.login_code_sent',
            $user->id,
            $user->email,
            request()->ip(),
            request()->userAgent(),
        );

        return $code;
    }

    /**
     * True only when $input matches the live login-code hash. Wrong guesses
     * count against the per-account ceiling (mirroring the reset flow): past
     * the limit the code is withdrawn so one intercepted email cannot be
     * retried indefinitely.
     */
    private function checkLoginCode(User $user, string $input): bool
    {
        $record = $this->liveLoginCode($user);

        if ($record === null) {
            return false;
        }

        $candidate = strtoupper(trim($input));

        if (! preg_match(PasswordResetCodeService::CODE_REGEX, $candidate)
            || ! Hash::check($candidate, $record['hash'])) {
            RateLimiter::hit($this->loginCodeFailKey($user), self::LOGIN_CODE_FAILURE_DECAY_SECONDS);

            if (RateLimiter::attempts($this->loginCodeFailKey($user)) > self::LOGIN_CODE_FAILURE_LIMIT) {
                Cache::forget($this->loginCodeCacheKey($user));
                RateLimiter::clear($this->loginCodeFailKey($user));
            }

            return false;
        }

        return true;
    }

    /**
     * Withdraw the login code and its failure counter (called the moment a
     * code verifies, so every code is single-use).
     */
    private function burnLoginCode(User $user): void
    {
        Cache::forget($this->loginCodeCacheKey($user));
        RateLimiter::clear($this->loginCodeFailKey($user));
    }

    /**
     * Same code format as password-reset codes (shared alphabet and shape)
     * but a SEPARATE random value in a SEPARATE store — cross-validation is
     * impossible by construction.
     */
    private function generateLoginCode(): string
    {
        $alphabet = PasswordResetCodeService::CODE_ALPHABET;
        $length = strlen($alphabet);
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, $length - 1)];
        }

        return $code;
    }

    private function loginCodeCacheKey(User $user): string
    {
        return 'two-factor-login-code:'.$user->getKey();
    }

    private function loginCodeFailKey(User $user): string
    {
        return 'two-factor-login-fail:'.$user->getKey();
    }

    private function loginCodeCooldownKey(User $user): string
    {
        return 'two-factor-login-cooldown:'.$user->getKey();
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
