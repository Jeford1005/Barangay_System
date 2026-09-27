<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Password Reset Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    {{-- Preheader: invisible preview text for inbox listings --}}
    <span style="display: none; max-height: 0; overflow: hidden;">Your Barangay Management System reset code is {{ $code }} — expires in {{ $expiresInMinutes }} minutes.</span>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 32px 16px;">
        <tr>
            <td align="center">
                {{-- 16px, not 50%: a percentage radius on a full-width table
                     turns the whole card into an oval and clips the wordmark,
                     the code and the footer against its curve. --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                    {{-- Dark brand header, mirroring the login page panel --}}
                    <tr>
                        <td style="background-color: #171717; background-image: linear-gradient(135deg, #171717 0%, #172554 100%); padding: 28px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="width: 44px;">
                                        <x-mail-logo :size="44" />
                                    </td>
                                    <td style="padding-left: 12px;">
                                        <div style="color: #ffffff; font-size: 15px; font-weight: 600; letter-spacing: -0.01em;">Barangay Management System</div>
                                        <div style="color: #a3a3a3; font-size: 12px; margin-top: 2px;">Office of the Barangay</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="background-color: #ffffff; padding: 32px;">
                            <h1 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 600; color: #171717; letter-spacing: -0.01em;">Reset your password</h1>
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #525252;">
                                Hello{{ isset($userName) && $userName ? ' '.$userName : '' }},<br>
                                Use the code below to choose a new password for your account.
                            </p>

                            {{-- The code --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 20px;">
                                        <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; margin-bottom: 8px;">Reset code</div>
                                        <div style="font-size: 32px; font-weight: 700; letter-spacing: 0.35em; color: #1d4ed8;">{{ $code }}</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 20px 0 0 0; font-size: 13px; line-height: 1.6; color: #737373; text-align: center;">
                                This code expires in <strong style="color: #404040;">{{ $expiresInMinutes }} minutes</strong> and can only be used once.<br>
                                Requesting a new code replaces this one.
                            </p>

                            <p style="margin: 24px 0 0 0; font-size: 13px; line-height: 1.6; color: #737373;">
                                Didn't request this? No action is needed — your account is safe, and this code will simply expire.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #fafafa; border-top: 1px solid #e5e5e5; padding: 20px 32px;">
                            <p style="margin: 0; font-size: 12px; line-height: 1.6; color: #a3a3a3;">
                                &copy; {{ date('Y') }} Barangay Management System · Office of the Barangay<br>
                                This is an automated message — please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
