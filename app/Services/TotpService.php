<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Time-based one-time passwords (RFC 6238, SHA-1, 30-second steps).
 *
 * Dependency-free crypto: base32 handling, HMAC-SHA1 dynamic truncation, and
 * the ±1 step clock-skew window in about forty lines. Kept here instead of a
 * package so office 2FA adds no new composer requirements. Replay tracking
 * below uses the default cache store (array in tests, file/redis in prod) —
 * still no new composer requirements.
 */
class TotpService
{
    public const STEP_SECONDS = 30;

    public const DIGITS = 6;

    public const SECRET_BYTES = 20;

    public const WINDOW_STEPS = 1;

    public const RECOVERY_CODE_COUNT = 8;

    /**
     * How long a consumed time-step stays rejected. A code is verifiable for
     * up to 3 steps (±1 window), so 90s covers its full remaining validity.
     */
    public const USED_STEP_TTL_SECONDS = 90;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Unambiguous alphabet for recovery codes: no 0/O, 1/I/L that an office
     * clerk could misread off a printed page.
     */
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * A fresh random secret, base32-encoded without padding (what an
     * authenticator app expects as the manual key).
     */
    public function generateSecret(int $bytes = self::SECRET_BYTES): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /**
     * The otpauth:// URI shown as TEXT (plus the manual key) so the office
     * user can enroll without any QR-image library in scope.
     */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$accountName);

        return 'otpauth://totp/'.$label
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::STEP_SECONDS;
    }

    /**
     * The code valid right now (or at $at). Tests use this to complete a
     * real enroll+confirm round-trip instead of asserting a fixed code.
     */
    public function currentCode(string $secret, ?int $at = null): string
    {
        return $this->codeForStep($secret, $this->stepFor($at ?? time()));
    }

    /**
     * True when $code matches the current step or one neighbour either way,
     * tolerating normal authenticator clock skew. Comparison is
     * length-safe; malformed input is simply rejected.
     */
    public function verify(string $secret, string $code, ?int $at = null, int $window = self::WINDOW_STEPS): bool
    {
        return $this->matchedStep($secret, $code, $at, $window) !== null;
    }

    /**
     * The time-step $code matched for $secret, or null when it matches none.
     * Exposed so callers can reject reuse of an already-consumed step
     * (TOTP codes stay valid for ~90s, so a bare verify() accepts replays).
     */
    public function matchedStep(string $secret, string $code, ?int $at = null, int $window = self::WINDOW_STEPS): ?int
    {
        $candidate = str_replace(' ', '', trim($code));

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $candidate)) {
            return null;
        }

        $step = $this->stepFor($at ?? time());

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForStep($secret, $step + $offset), $candidate)) {
                return $step + $offset;
            }
        }

        return null;
    }

    /**
     * The current time-step. Exposed for replay bookkeeping and tests.
     */
    public function currentStep(?int $at = null): int
    {
        return $this->stepFor($at ?? time());
    }

    /**
     * Strict base32 check for TOTP secrets. The decoder below silently skips
     * unknown characters, so an unenrolled (session) or stored secret must be
     * validated with this first — never fed straight into codeForStep().
     */
    public static function isValidSecret(string $secret): bool
    {
        return (bool) preg_match('/\A[A-Z2-7]{16,128}\z/', $secret);
    }

    /**
     * Cache key marking a (scope, step) pair as consumed. Scope always
     * includes the user id, so one account's codes never burn another's.
     */
    public static function usedStepCacheKey(string $scope, int $step): string
    {
        $clean = (string) preg_replace('/[^A-Za-z0-9_:\-.]/', '', $scope);

        return 'two_factor.used_step:'.$clean.':'.$step;
    }

    public function stepAlreadyUsed(string $scope, int $step): bool
    {
        return Cache::has(self::usedStepCacheKey($scope, $step));
    }

    public function markStepUsed(string $scope, int $step): void
    {
        Cache::put(
            self::usedStepCacheKey($scope, $step),
            true,
            now()->addSeconds(self::USED_STEP_TTL_SECONDS),
        );
    }

    /**
     * Single-use backup codes, shown once at enroll and stored only as
     * hashes. Displayed grouped (XXXXX-XXXXX) for readability; matching
     * ignores dashes, spaces, and case via normalizeRecoveryCode().
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = self::RECOVERY_CODE_COUNT): array
    {
        $codes = [];
        $alphabetLength = strlen(self::RECOVERY_ALPHABET);

        for ($i = 0; $i < $count; $i++) {
            $raw = '';

            for ($j = 0; $j < 10; $j++) {
                $raw .= self::RECOVERY_ALPHABET[random_int(0, $alphabetLength - 1)];
            }

            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5, 5);
        }

        return $codes;
    }

    public static function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(str_replace([' ', '-'], '', trim($code)));
    }

    public function codeForStep(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);

        // 8-byte big-endian counter: pack('N*', high, low). The step never
        // reaches 2^32 in practice, so the high word stays zero.
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);

        // Dynamic truncation: the low nibble of the last byte picks the
        // 4-byte slice, masked to 31 bits, reduced to the digit count.
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function stepFor(int $timestamp): int
    {
        return (int) floor($timestamp / self::STEP_SECONDS);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $secret): string
    {
        $clean = rtrim(strtoupper(str_replace([' ', '-'], '', $secret)), '=');
        $bits = '';

        foreach (str_split($clean) as $char) {
            $position = strpos(self::BASE32_ALPHABET, $char);

            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
