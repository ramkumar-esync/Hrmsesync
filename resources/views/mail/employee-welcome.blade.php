{{-- Plain, inline-styled HTML so it renders consistently across mail clients. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
</head>
<body style="margin:0; padding:0; background:#f4f5f3; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#1c1f24;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f3; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#ffffff; border:1px solid #e4e6e1; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#0f4c48; padding:24px 32px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:600; letter-spacing:0.01em;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px; font-size:16px;">Hi {{ $name }},</p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#3a3f45;">
                                An account has been created for you on the
                                {{ config('app.name') }}. You can view your payslips, apply for
                                leave, and submit your monthly attendance there.
                            </p>

                            @if ($password)
                                <p style="margin:0 0 8px; font-size:15px; line-height:1.6; color:#3a3f45;">
                                    Sign in with these details:
                                </p>

                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef5f4; border:1px solid #cfe3e0; border-radius:8px; margin:8px 0 20px;">
                                    <tr>
                                        <td style="padding:16px 20px;">
                                            <p style="margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280;">Portal address</p>
                                            <p style="margin:0 0 14px; font-size:15px; font-family:'SF Mono',Menlo,Consolas,monospace;">
                                                <a href="{{ $appUrl }}" style="color:#0f4c48; text-decoration:none;">{{ $appUrl }}</a>
                                            </p>
                                            <p style="margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280;">Email</p>
                                            <p style="margin:0 0 14px; font-size:15px; font-family:'SF Mono',Menlo,Consolas,monospace; color:#1c1f24;">{{ $email }}</p>
                                            <p style="margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280;">Temporary password</p>
                                            <p style="margin:0; font-size:18px; font-weight:600; font-family:'SF Mono',Menlo,Consolas,monospace; color:#0f4c48; letter-spacing:0.02em;">{{ $password }}</p>
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin:0 0 20px; font-size:13px; line-height:1.6; color:#6b7280;">
                                    Please change this password after you sign in. If you didn't expect
                                    this email, let HR know.
                                </p>

                                <a href="{{ $appUrl }}" style="display:inline-block; background:#0f4c48; color:#ffffff; text-decoration:none; font-size:15px; font-weight:600; padding:12px 24px; border-radius:8px;">
                                    Sign in
                                </a>
                            @else
                                <p style="margin:0 0 12px; font-size:15px; line-height:1.6; color:#3a3f45;">
                                    Your employee record is set up. When a login is enabled for you,
                                    you'll receive your sign-in details separately.
                                </p>
                                <p style="margin:0 0 20px; font-size:15px; line-height:1.6; color:#3a3f45;">
                                    The portal is at
                                    <a href="{{ $appUrl }}" style="color:#0f4c48;">{{ $appUrl }}</a>.
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px; border-top:1px solid #e4e6e1;">
                            <p style="margin:0; font-size:12px; color:#9ca3af;">
                                This is an automated message from {{ config('app.name') }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>