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
            // Deliberately identical for known and unknown emails: a "wait"
            // message must not confirm that an account exists.
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

        // Only active accounts may reset. Pending, rejected, suspended, and
        // unknown addresses all receive the same generic response.
        $user = User::where('email', $request->input('email'))
            ->where('status', 'approved')
            ->whereNull('suspended_at')
            ->first();

        if ($user) {
            $this->resetCodes->issue($user);

            AuditLog::record(
                'password_reset.code_requested',
                $user->id,
                $user->email,
                $request->ip(),
                $request->userAgent(),
            );
        }

        // Start the cooldown regardless of whether the account exists.
        $request->session()->put('password_reset.sent_at', now()->timestamp);

        $message = 'If that email address exists in our records, a 6-character reset code has been sent to it.';

        // Identical response for known and unknown emails — prevents account enumeration.
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
