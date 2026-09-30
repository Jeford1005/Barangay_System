{{-- Canonical peso display. Mirrors peso() in app/helpers.php (composer has no
     files-autoload entry, so this component — not the helper — is the wired
     path for views). Free-word labels render only when the amount is
     genuinely zero, matching the verified per-view logic. --}}
@props(['amount' => null, 'free' => null])@php
$moneyValue = is_numeric($amount) ? (float) $amount : 0.0;
$moneyText = function_exists('peso') ? peso($amount) : '₱'.number_format($moneyValue, 2);
@endphp@if ($free !== null && $moneyValue == 0.0){{ $free }}@else{{ $moneyText }}@endif
