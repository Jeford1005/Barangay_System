<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Request Approved</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden;">Your {{ $request->document->title }} request has been approved — claim it at the barangay hall.</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #171717; background-image: linear-gradient(135deg, #171717, #14532d); padding: 28px 32px;">
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
                            <h1 style="margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #171717;">Good news, {{ $userName ?: 'Resident' }}!</h1>
                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #525252;">
                                Your request for a <strong>{{ $request->document->title }}</strong>
                                (Request #{{ $request->id }}, purpose: {{ $request->purpose }}) has been approved.
                                Your certificate is ready for release at the barangay hall.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 14px 18px; font-size: 13px; line-height: 1.7; color: #14532d;">
                                        <strong>Control No.:</strong> {{ $request->issuance?->control_number ?? 'issuance record unavailable' }}<br>
                                        <strong>Copies:</strong> {{ $request->issuance?->copies ?? $request->copies }}<br>
                                        <strong>Total fee:</strong> @if ((float) ($request->issuance?->fee ?? 0) > 0) <x-money :amount="$request->issuance->fee" /> @else Free of charge @endif
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #525252;">
                                Please bring a valid ID when claiming.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="background-color: #16a34a; border-radius: 8px;">
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
