<?php

namespace App\Services;

/**
 * Stateless HMAC-signed certificate verification.
 *
 * The QR printed on a certificate encodes an absolute verify URL of the
 * form /verify/{control_number}/{token}, where the token carries a version
 * prefix (`v1` + 14 hex chars, still 16 chars total) followed by a
 * truncated HMAC-SHA256 of "version:control_number" keyed with the app
 * key. Guessing a neighbouring control number is useless without its
 * token, and no database column or migration is needed — verification
 * recomputes the HMAC and compares with hash_equals().
 *
 * Key + version rotation path:
 * - APP_KEY rotation: put the superseded key in APP_PREVIOUS_KEYS
 *   (config `app.previous_keys`, comma-separated). isValid() tries the
 *   current key first, then every previous key, so already-printed QRs
 *   keep verifying after a roll. Drop the old key once printed stock has
 *   cycled.
 * - Version roll (v1 -> v2): bump TOKEN_VERSION. Minting switches to the
 *   new version immediately, but KEEP the older version branch (and the
 *   legacy branch below) inside isValid() for one full print cycle so
 *   certificates already taped to walls keep verifying during transition.
 *
 * Legacy: tokens minted before versioning (bare 16 hex chars, HMAC over
 * the raw control number) are still honored while any such QRs may be in
 * the wild. Minting never produces them anymore.
 *
 * QR rendering decision (2026-10-02): `composer require
 * chillerlan/php-qrcode` was tried first with a 3-minute timeout, but the
 * package download stalled (metadata resolved v6.0.1, dist zip never
 * arrived), so the require was reverted — composer.json/composer.lock are
 * untouched. A vendored pure-PHP encoder is too big to write safely, so
 * the print view renders the QR client-side with the tiny vendored,
 * dependency-free MIT library in public/js/qrcode.js
 * (davidshimjs/qrcodejs, works offline on XAMPP — no CDN). If that script
 * ever fails to load, the placeholder box plus the printed verify URL in
 * text remain as the fallback.
 */
class CertificateVerification
{
    /**
     * Total token chars in the URL: version prefix + hex tail. Long enough
     * to stop guessing (56-bit HMAC tail), short enough to stay scannable
     * in a small QR. The verify controller validates size:16 — keep the
     * total here in lockstep with that rule.
     */
    public const TOKEN_LENGTH = 16;

    /**
     * Version prefix embedded at the head of every minted token, so a
     * future v2 can be told apart from v1 without a migration.
     */
    public const TOKEN_VERSION = 'v1';

    public const VERSION_PREFIX_LENGTH = 2;

    /**
     * Hex chars of the HMAC kept after the prefix (2 + 14 = TOKEN_LENGTH).
     */
    public const TOKEN_HEX_LENGTH = 14;

    /**
     * Upper bound for a scanned control number. Mirrors
     * CertificateIssuance::CODE_MAX (widened to 48 on 2026-09-30).
     */
    public const CODE_MAX = 48;

    public static function token(string $controlNumber): string
    {
        return static::mint($controlNumber, (string) config('app.key'), static::TOKEN_VERSION);
    }

    public static function isValid(string $controlNumber, string $token): bool
    {
        $token = strtolower($token);

        // Current versioned format: v1 + 14 hex.
        if (preg_match('/^v1[0-9a-f]{14}$/', $token) === 1) {
            foreach (static::candidateKeys() as $key) {
                if (hash_equals(static::mint($controlNumber, $key, 'v1'), $token)) {
                    return true;
                }
            }

            return false;
        }

        // Legacy pre-versioning format: bare 16 hex over the raw number.
        // Honored during transition only — minting never produces it.
        if (preg_match('/^[0-9a-f]{16}$/', $token) === 1) {
            foreach (static::candidateKeys() as $key) {
                if (hash_equals(static::legacyToken($controlNumber, $key), $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Absolute verify URL. route() is APP_URL-safe (uses config app.url,
     * never a hardcoded host), so printed QR codes stay correct across
     * environments.
     */
    public static function url(string $controlNumber): string
    {
        return route('certificates.verify', [
            'control_number' => $controlNumber,
            'token' => static::token($controlNumber),
        ]);
    }

    /**
     * Mint a token for one version + key. The version participates in the
     * HMAC input ("v1:number") so a v1 token can never validate as v2.
     */
    private static function mint(string $controlNumber, string $key, string $version): string
    {
        return $version.substr(
            hash_hmac('sha256', $version.':'.$controlNumber, $key),
            0,
            static::TOKEN_LENGTH - strlen($version)
        );
    }

    /**
     * Pre-versioning mint (raw control number, no prefix) — verification
     * of already-printed stock only.
     */
    private static function legacyToken(string $controlNumber, string $key): string
    {
        return substr(hash_hmac('sha256', $controlNumber, $key), 0, static::TOKEN_LENGTH);
    }

    /**
     * Current app key first, then every superseded key from
     * APP_PREVIOUS_KEYS, so verification survives a key roll.
     *
     * @return list<string>
     */
    private static function candidateKeys(): array
    {
        return array_values(array_filter(array_merge(
            [(string) config('app.key')],
            (array) config('app.previous_keys', [])
        ), fn ($key) => is_string($key) && $key !== ''));
    }
}
