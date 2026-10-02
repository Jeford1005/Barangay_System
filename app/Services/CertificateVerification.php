<?php

namespace App\Services;

/**
 * Stateless HMAC-signed certificate verification.
 *
 * The QR printed on a certificate encodes an absolute verify URL of the
 * form /verify/{control_number}/{token}, where the token is a truncated
 * HMAC-SHA256 of the control number keyed with the app key. Guessing a
 * neighbouring control number is useless without its token, and no
 * database column or migration is needed — verification recomputes the
 * HMAC and compares with hash_equals().
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
     * Hex chars of the HMAC kept in the URL: long enough to stop guessing
     * (64 bits), short enough to stay scannable in a small QR.
     */
    public const TOKEN_LENGTH = 16;

    /**
     * Upper bound for a scanned control number. Mirrors
     * CertificateIssuance::CODE_MAX (widened to 48 on 2026-09-30).
     */
    public const CODE_MAX = 48;

    public static function token(string $controlNumber): string
    {
        return substr(
            hash_hmac('sha256', $controlNumber, (string) config('app.key')),
            0,
            static::TOKEN_LENGTH
        );
    }

    public static function isValid(string $controlNumber, string $token): bool
    {
        return hash_equals(static::token($controlNumber), strtolower($token));
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
}
