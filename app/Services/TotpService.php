<?php

namespace App\Services;

/**
 * Time-based one-time passwords (RFC 6238, SHA-1, 30-second steps).
 *
 * Dependency-free: base32 handling, HMAC-SHA1 dynamic truncation, and the
 * ±1 step clock-skew window in about forty lines. Kept here instead of a
 * package so office 2FA adds no new composer requirements.
 */
class TotpService
{
    public const STEP_SECONDS = 30;

    public const DIGITS = 6;

    public const SECRET_BYTES = 20;

    public const WINDOW_STEPS = 1;

    public const RECOVERY_CODE_COUNT = 8;

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
        $candidate = str_replace(' ', '', trim($code));

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $candidate)) {
            return false;
        }

        $step = $this->stepFor($at ?? time());

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForStep($secret, $step + $offset), $candidate)) {
                return true;
            }
        }

        return false;
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
