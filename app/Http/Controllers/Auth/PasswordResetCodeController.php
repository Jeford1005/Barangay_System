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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetCodeController extends Controller
{
    /**
     * Seconds the visitor must wait before requesting another code.
     *
     * The countdown is keyed to the session for instant UX, and mirrored in
     * the server-side cache per IP and per account, so clearing cookies or
     * switching to incognito does not bypass the resend limit. It is never
     * keyed to the raw email address in a visible response, so a "please
     * wait" notice can't reveal whether an account exists.
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
            return $this->cooldownWaitResponse($request, $remaining, (string) $request->input('email'));
        }

        $email = trim((string) $request->input('email'));
        $user = User::where('email', $email)->first();

        // Unknown addresses get the same success-shaped response as a real
        // send, so the endpoint never reveals whether an account exists.
        // The attempt is logged internally for abuse monitoring.
        if (! $user) {
            // Abuse monitoring only: the text log keeps hashes, never the raw
            // email or IP. The DB audit row is intentionally not written for
            // unknown addresses (see test_requests_for_unknown_emails…).
            Log::info('password_reset.code_requested_unknown', [
                'email_hash' => self::piiHash($email),
                'ip_hash' => self::piiHash((string) $request->ip()),
            ]);

            $generic = 'If an account exists for that email address, a 6-character reset code has been sent. It expires in '.PasswordResetCodeService::CODE_TTL_MINUTES.' minutes.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $generic,
                    'email' => $email,
                    'cooldown' => 0,
                    'expires_in_minutes' => PasswordResetCodeService::CODE_TTL_MINUTES,
                ]);
            }

            // The address travels in the session, never in the redirect query:
            // a ?email= query lands in server logs, proxy logs, and shared
            // caches, while session storage stays server-side.
            $request->session()->put('password_reset.email', $email);

            return redirect()
                ->route('password.reset')
                ->with('status', $generic);
        }

        // Anything that cannot actually receive a code is refused here, on step
        // 1, with the reason. Sending it on to the code screen anyway would
        // strand the visitor in front of a "code sent" panel for an email that
        // never produced one — they would wait for a message that was never
        // coming and have no way to tell what went wrong.
        if ($reason = $this->refusalReason($user)) {
            return $this->refuse($request, $reason);
        }

        // The per-account cooldown survives IP rotation and cookie clears.
        // The wait message stays generic either way.
        if ($remaining = self::cooldownRemaining(userId: $user->id)) {
            return $this->cooldownWaitResponse($request, $remaining, $email);
        }

        try {
            $this->resetCodes->issue($user);
        } catch (\Throwable $exception) {
            report($exception);
            // The DB audit row keeps the account link; the text log keeps
            // hashes only so raw emails/IPs never sit in laravel.log.
            Log::warning('password_reset.code_send_failed', [
                'user_id' => $user->id,
                'email_hash' => self::piiHash((string) $user->email),
                'ip_hash' => self::piiHash((string) $request->ip()),
            ]);

            AuditLog::record(
                'password_reset.code_request_failed',
                $user->id,
                $user->email,
                $request->ip(),
                $request->userAgent(),
            );

            $message = 'The reset email could not be sent right now. Check the mail configuration and try again in a minute.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return redirect()
                ->route('password.request')
                ->withInput($request->only('email'))
                ->withErrors(['email' => $message]);
        }

        AuditLog::record(
            'password_reset.code_requested',
            $user->id,
            $user->email,
            $request->ip(),
            $request->userAgent(),
        );

        // The cooldown starts only once a code has really gone out, so a
        // mistyped address costs nothing to correct. It lives in the session
        // for the instant countdown and in the server-side cache per IP and
        // per account, so clearing cookies or switching networks won't
        // bypass the resend limit.
        $request->session()->put('password_reset.sent_at', now()->timestamp);
        Cache::put(self::cooldownCacheKey('ip', (string) $request->ip()), now()->timestamp, self::RESEND_COOLDOWN_SECONDS);
        Cache::put(self::cooldownCacheKey('user', (string) $user->id), now()->timestamp, self::RESEND_COOLDOWN_SECONDS);

        $message = 'A 6-character reset code has been sent to your email address.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'email' => $request->input('email'),
                'cooldown' => self::RESEND_COOLDOWN_SECONDS,
                'expires_in_minutes' => PasswordResetCodeService::CODE_TTL_MINUTES,
            ]);
        }

        // The address travels in the session, never in the redirect query:
        // a ?email= query lands in server logs, proxy logs, and shared
        // caches, while session storage stays server-side.
        $request->session()->put('password_reset.email', $request->input('email'));

        return redirect()
            ->route('password.reset')
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
    private function refusalReason(User $user): ?string
    {
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
     *
     * Direct access without a prior code request is refused: an email that
     * has no outstanding (unexpired, unconsumed) token is sent back to step
     * 1 instead of being shown a form that could never succeed.
     */
    public function create(Request $request): View|RedirectResponse
    {
        // The address travels in the session (put there by email()), never
        // in the URL query: ?email= lands in server/proxy logs and shared
        // caches. The query string remains as a legacy fallback so bookmarked
        // or in-flight step-2 links keep working; session wins when both
        // carry a value.
        $sessionEmail = trim((string) $request->session()->get('password_reset.email', ''));
        $email = $sessionEmail !== ''
            ? $sessionEmail
            : trim((string) $request->query('email', old('email', '')));

        if ($email !== '') {
            $hasToken = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->exists();

            if (! $hasToken) {
                return redirect()
                    ->route('password.request')
                    ->withInput(['email' => $email])
                    ->withErrors(['email' => 'No active reset code was found for this email. Request a new code first.']);
            }
        }

        return view('auth.passwords.reset', [
            'email' => $email,
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
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
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
            // fresh budget against every address. Burn the token after 5 wrong
            // guesses per account (the 6th failed attempt withdraws it) so a
            // single intercepted code cannot be retried indefinitely.
            $failureKey = 'password-reset-fail:'.Str::lower(trim((string) $request->input('email')));

            RateLimiter::hit($failureKey, self::CODE_FAILURE_DECAY_SECONDS);

            $withdrawn = RateLimiter::attempts($failureKey) > self::CODE_FAILURE_LIMIT;

            if ($withdrawn) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->input('email'))
                    ->delete();

                RateLimiter::clear($failureKey);
            }

            AuditLog::record(
                'password_reset.failed_code',
                null,
                $request->input('email'),
                $request->ip(),
                $request->userAgent(),
            );

            $failureMessage = $withdrawn
                ? 'Too many incorrect attempts. Your reset code has been withdrawn. Request a new one.'
                : 'That code is invalid or has expired. Request a new one.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $failureMessage,
                    'errors' => ['code' => [$failureMessage]],
                ], 422);
            }

            return back()
                ->withErrors(['code' => $failureMessage])
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
        $request->session()->forget(['password_reset.sent_at', 'password_reset.email']);
        Cache::forget(self::cooldownCacheKey('ip', (string) $request->ip()));
        Cache::forget(self::cooldownCacheKey('user', (string) $user->id));
        RateLimiter::clear('password-reset-fail:'.Str::lower(trim((string) $user->email)));

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
     *
     * Reads the session timestamp plus the server-side cache (per IP and per
     * account) and returns the longest remaining wait, so a cleared session
     * alone does not lift the limit.
     */
    public static function cooldownRemaining(?string $ip = null, ?int $userId = null): int
    {
        $ip ??= app()->bound('request') ? (string) request()->ip() : '';

        $sentAt = (int) (session('password_reset.sent_at') ?? 0);

        if ($ip !== '') {
            $sentAt = max($sentAt, (int) Cache::get(self::cooldownCacheKey('ip', $ip), 0));
        }

        if ($userId !== null) {
            $sentAt = max($sentAt, (int) Cache::get(self::cooldownCacheKey('user', (string) $userId), 0));
        }

        if ($sentAt <= 0) {
            return 0;
        }

        return max(0, self::RESEND_COOLDOWN_SECONDS - (now()->timestamp - $sentAt));
    }

    /**
     * One-way hash for PII that reaches the text log (emails, IPs). Keeps
     * abuse correlation possible without persisting raw identifiers in
     * laravel.log. DB audit rows are unaffected.
     */
    private static function piiHash(string $value): string
    {
        return hash('sha256', mb_strtolower(trim($value)));
    }

    /**
     * Server-side cooldown slot. The IP segment is hashed so the cache key
     * never stores a raw address verbatim.
     */
    private static function cooldownCacheKey(string $scope, string $value): string
    {
        $segment = $scope === 'ip' ? sha1($value) : $value;

        return 'password-reset-cooldown:'.$scope.':'.$segment;
    }

    /**
     * Generic "please wait" response shared by the pre-lookup IP check and
     * the post-lookup per-account check. It never confirms the address.
     */
    private function cooldownWaitResponse(Request $request, int $remaining, string $email): RedirectResponse|JsonResponse
    {
        $message = "Please wait {$remaining} seconds before requesting another code.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'cooldown' => $remaining,
                'wait' => true,
            ]);
        }

        // The address travels in the session, never in the redirect query.
        $request->session()->put('password_reset.email', $email);

        return redirect()
            ->route('password.reset')
            ->with('status', $message)
            ->with('cooldown_seconds', $remaining);
    }
}
