<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PasswordResetCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetCodeController extends Controller
{
    /**
     * Seconds the visitor must wait before requesting another code.
     * Keyed to the session, never to the email address, so a "please wait"
     * response can't reveal whether an account exists.
     */
    private const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Wrong codes allowed per email before the token is withdrawn. The route
     * throttle is per IP, so this is the per-account ceiling.
     */
    private const CODE_FAILURE_LIMIT = 5;

    /** Seconds the per-account failure counter survives. */
    private const CODE_FAILURE_DECAY_SECONDS = 900;

    public function __construct(
        private readonly PasswordResetCodeService $resetCodes,
    ) {}

    /**
     * Show the "request a reset code" screen.
     */
    public function request(): View
    {
        return view('auth.passwords.email', [
            'cooldownSeconds' => $this->cooldownRemaining(),
        ]);
    }

    /**
     * Generate a code, store its hash, and email it to the user.
     */
    public function email(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        if ($remaining = $this->cooldownRemaining()) {
            // The wait message stays generic: it is a rate-limit notice, not a
            // verdict on the address.
            $message = "Please wait {$remaining} seconds before requesting another code.";

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'cooldown' => $remaining,
                    'wait' => true,
                ]);
            }

            return redirect()
                ->route('password.reset', ['email' => $request->input('email')])
                ->with('status', $message)
                ->with('cooldown_seconds', $remaining);
        }

        $user = User::where('email', $request->input('email'))->first();

        // Anything that cannot actually receive a code is refused here, on step
        // 1, with the reason. Sending it on to the code screen anyway would
        // strand the visitor in front of a "code sent" panel for an email that
        // never produced one — they would wait for a message that was never
        // coming and have no way to tell what went wrong.
        if ($reason = $this->refusalReason($user)) {
            return $this->refuse($request, $reason);
        }

        $this->resetCodes->issue($user);

        AuditLog::record(
            'password_reset.code_requested',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
        );

        // The cooldown starts only once a code has really gone out, so a
        // mistyped address costs nothing to correct.
        $request->session()->put('password_reset.sent_at', now()->timestamp);

        $message = 'A 6-character reset code has been sent to your email address.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'email' => $request->input('email'),
                'cooldown' => self::RESEND_COOLDOWN_SECONDS,
                'expires_in_minutes' => PasswordResetCodeService::CODE_TTL_MINUTES,
            ]);
        }

        return redirect()
            ->route('password.reset', ['email' => $request->input('email')])
            ->with('status', $message)
            ->with('cooldown_seconds', self::RESEND_COOLDOWN_SECONDS);
    }

    /**
     * Why this address may not be sent a code, or null when it may.
     *
     * The pending / rejected / suspended wording is LoginRequest's own, so a
     * visitor hears the same reason from both doors instead of a clear
     * explanation at sign-in and a dead end here.
     */
    private function refusalReason(?User $user): ?string
    {
        if (! $user) {
            return "We couldn't find an account with that email address. Check the spelling, or register for an account.";
        }

        if ($user->isSuspended()) {
            return 'This account has been suspended. Please contact the barangay office.';
        }

        if (! $user->isApproved()) {
            return $user->isPending()
                ? 'Your account is still awaiting approval from the barangay office. We will email you once it is approved.'
                : 'This account was not approved. Please contact the barangay office for assistance.';
        }

        return null;
    }

    /**
     * Hold the visitor on the email step, keeping their address in the field so
     * a typo can simply be corrected rather than retyped.
     */
    private function refuse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return redirect()
            ->route('password.request')
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }

    /**
     * Show the "enter code + new password" screen.
     */
    public function create(Request $request): View
    {
        return view('auth.passwords.reset', [
            'email' => $request->query('email', old('email', '')),
            'expiresInMinutes' => PasswordResetCodeService::CODE_TTL_MINUTES,
            'cooldownSeconds' => $this->cooldownRemaining(),
        ]);
    }

    /**
     * Verify the code and set the new password.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:'.PasswordResetCodeService::CODE_REGEX],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->input('email'))
            ->first();

        $expiresAt = $reset
            ? Carbon::parse($reset->created_at)->addMinutes(PasswordResetCodeService::CODE_TTL_MINUTES)
            : null;

        $expired = ! $reset
            || $expiresAt->isPast()
            || ! hash_equals((string) $reset->token, hash('sha256', strtoupper($request->input('code'))));

        if ($expired) {
            // The route throttle is IP scoped, so a rotating attacker gets a
            // fresh budget against every address. Burn the token after a few
            // wrong guesses per account so a single intercepted code cannot be
            // retried indefinitely from different hosts.
            $failureKey = 'password-reset-fail:'.Str::lower((string) $request->input('email'));

            if (RateLimiter::tooManyAttempts($failureKey, self::CODE_FAILURE_LIMIT)) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->input('email'))
                    ->delete();

                RateLimiter::clear($failureKey);
            } else {
                RateLimiter::hit($failureKey, self::CODE_FAILURE_DECAY_SECONDS);
            }

            AuditLog::record(
                'password_reset.failed_code',
                null,
                $request->input('email'),
                $request->ip(),
                $request->userAgent(),
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'That code is invalid or has expired. Request a new one.',
                    'errors' => ['code' => ['That code is invalid or has expired. Request a new one.']],
                ], 422);
            }

            return back()
                ->withErrors(['code' => 'That code is invalid or has expired. Request a new one.'])
                ->withInput($request->only('email'));
        }

        $user = User::where('email', $request->input('email'))
            ->where('status', 'approved')
            ->whereNull('suspended_at')
            ->first();

        if (! $user) {
            AuditLog::record(
                'password_reset.failed_code',
                null,
                $request->input('email'),
                $request->ip(),
                $request->userAgent(),
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'That code is invalid or has expired. Request a new one.',
                    'errors' => ['code' => ['That code is invalid or has expired. Request a new one.']],
                ], 422);
            }

            return back()
                ->withErrors(['code' => 'That code is invalid or has expired. Request a new one.'])
                ->withInput($request->only('email'));
        }

        // The User model's 'hashed' cast bcrypts this automatically.
        $user->password = $request->input('password');
        $user->setRememberToken(null);
        $user->save();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        // Single use: burn the code and clear the cooldown.
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $request->session()->forget('password_reset.sent_at');
        RateLimiter::clear('password-reset-fail:'.Str::lower((string) $user->email));

        AuditLog::record(
            'password_reset.completed',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
        );

        $message = 'Your password has been reset. Sign in with your new password.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route('login')
            ->with('status', $message);
    }

    /**
     * Seconds left before another code may be requested.
     * Public + static so the login page can show an honest countdown too.
     */
    public static function cooldownRemaining(): int
    {
        $sentAt = session('password_reset.sent_at');

        if (! $sentAt) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (now()->timestamp - (int) $sentAt));
    }
}
