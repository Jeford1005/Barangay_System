<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Request Not Approved</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden;">Update on your {{ $request->document->title }} request.</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #171717; background-image: linear-gradient(135deg, #171717, #7f1d1d); padding: 28px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-right: 12px;">
                                        <x-mail-logo :size="40" />
                                    </td>
                                    <td>
                                        <div style="color: #ffffff; font-size: 14px; font-weight: 600;">Barangay Management System</div>
                                        <div style="color: #a3a3a3; font-size: 12px;">Office of the Barangay</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px;">
                            <h1 style="margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #171717;">Request update, {{ $userName ?: 'Resident' }}</h1>
                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #525252;">
                                We're sorry — your request for a <strong>{{ $request->document->title }}</strong>
                                (Request #{{ $request->id }}, purpose: {{ $request->purpose }}) was not approved.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 14px 18px; font-size: 13px; line-height: 1.7; color: #7f1d1d;">
                                        <strong>Reason:</strong><br>
                                        {{ $reason ?: 'No reason was provided. Please contact the barangay office for details.' }}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #525252;">
                                If you believe this was a mistake or you'd like to submit a new request with
                                different details, you may do so from your account.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="background-color: #2563eb; border-radius: 8px;">
                                        <a href="{{ route('resident.requests') }}" style="display: inline-block; padding: 12px 24px; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none;">View my requests</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 16px 32px; border-top: 1px solid #f5f5f5;">
                            <p style="margin: 0; font-size: 12px; color: #a3a3a3;">
                                This is an automated message from the Barangay Management System. Please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
