@props([
    'size' => 40,
    'alt' => 'Barangay seal',
])

@php
    // The seal is inlined as a data URI on purpose.
    //
    // `asset()` builds an absolute URL from APP_URL, which in a local or
    // not-yet-deployed setup is http://localhost — a host that only exists on
    // this machine. The recipient's mail client would try to load the logo from
    // their own computer and show a broken image. Inlining removes the hosting
    // dependency entirely, so the letterhead renders wherever it is opened.
    //
    // Known limit: desktop Outlook does not support data URIs. The alt text and
    // the wordmark beside it carry the meaning there, so the letter still reads.
    static $cached = null;

    if ($cached === null) {
        $path = public_path('images/seal-square.png');
        $cached = is_file($path)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path))
            : '';
    }
@endphp
@if ($cached !== '')
    <img src="{{ $cached }}" alt="{{ $alt }}" width="{{ $size }}" height="{{ $size }}"
        style="display: block; width: {{ $size }}px; height: {{ $size }}px; border-radius: 50%; border: 2px solid #ffffff;">
@else
    {{-- Fallback: the wordmark beside this still identifies the office. --}}
    <span style="display: inline-block; width: {{ $size }}px; height: {{ $size }}px; border-radius: 50%; border: 2px solid #ffffff;"></span>
@endif
