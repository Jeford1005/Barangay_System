<?php

// Central display conventions for user-facing values (English only).
//
// NOTE: composer.json declares no "files" autoload entry (left untouched on
// purpose), so Blade views must NOT call peso() directly. The wired path for
// views is the <x-money> anonymous component, which mirrors the logic below.
// Keep the two in sync.

if (! defined('APP_DISPLAY_UNKNOWN')) {
    define('APP_DISPLAY_UNKNOWN', 'Unknown');
}

if (! function_exists('peso')) {
    /**
     * Format an amount as Philippine pesos: ₱1,234.56.
     * Null-safe: null or '' renders as ₱0.00, never blank or an error.
     */
    function peso(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '₱0.00';
        }

        return '₱'.number_format((float) $amount, 2);
    }
}

if (! function_exists('display_unknown')) {
    /**
     * Fallback label for unavailable values (storage size, counts, actors).
     * Always English; prefer this over scattering 'Unknown' literals.
     */
    function display_unknown(): string
    {
        return APP_DISPLAY_UNKNOWN;
    }
}
