BARANGAY MANAGEMENT SYSTEM
==========================

Reset your password

Hello{{ isset($userName) && $userName ? ' '.$userName : '' }},

Use the code below to choose a new password for your account:

    >>  {{ $code }}  <<

This code expires in {{ $expiresInMinutes }} minutes and can only be used once.
Requesting a new code replaces this one.

Didn't request this? No action is needed — your account is safe.

© {{ date('Y') }} Barangay Management System · Office of the Barangay
