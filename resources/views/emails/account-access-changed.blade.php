<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Barangay account update</title>
</head>
<body style="margin:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#171717;">
    <div style="max-width:600px;margin:0 auto;padding:32px 16px;">
        <div style="background:#171717;color:#fff;padding:20px 24px;font-weight:700;">Barangay Management System</div>
        <div style="background:#fff;padding:28px 24px;border:1px solid #e5e5e5;border-top:0;">
            <p>Hello {{ $userName ?: 'Resident' }},</p>

            @if ($change === 'suspended')
                <p>Your barangay account has been suspended.</p>
                @if ($reason)
                    <p><strong>Reason:</strong> {{ $reason }}</p>
                @endif
                <p>Please contact the barangay office if you believe this is a mistake.</p>
            @elseif ($change === 'reactivated')
                <p>Your barangay account has been reactivated. You can sign in again using your existing credentials.</p>
            @elseif ($change === 'role_changed')
                <p>Your barangay account role has changed from <strong>{{ $fromRole === 'admin' ? 'Administrator' : ucfirst($fromRole) }}</strong> to <strong>{{ $toRole === 'admin' ? 'Administrator' : ucfirst($toRole) }}</strong>.</p>
            @else
                <p>Your barangay account details have been updated.</p>
            @endif

            <p style="margin-top:28px;">— Barangay Management System</p>
        </div>
    </div>
</body>
</html>
