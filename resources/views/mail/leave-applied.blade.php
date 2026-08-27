{{-- Plain, inline-styled HTML so it renders consistently across mail clients. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave request</title>
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
                            <p style="margin:0 0 16px; font-size:16px;">A leave request needs review</p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#3a3f45;">
                                <strong>{{ $employeeName }}</strong> has applied for leave.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f8f6; border:1px solid #e4e6e1; border-radius:8px; margin:8px 0 20px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280;">Type</p>
                                        <p style="margin:0 0 14px; font-size:15px; color:#1c1f24;">{{ $leaveType }}</p>
                                        <p style="margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280;">Dates</p>
                                        <p style="margin:0 0 14px; font-size:15px; font-family:'SF Mono',Menlo,Consolas,monospace; color:#1c1f24;">{{ $dates }}</p>
                                        <p style="margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280;">Working days</p>
                                        <p style="margin:0 {{ $reason ? '0 14px' : '0' }}; font-size:15px; color:#1c1f24;">{{ rtrim(rtrim(number_format($workingDays, 1), '0'), '.') }}</p>
                                        @if ($reason)
                                            <p style="margin:0 0 4px; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280;">Reason</p>
                                            <p style="margin:0; font-size:15px; line-height:1.5; color:#3a3f45;">{{ $reason }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <a href="{{ $appUrl }}/approvals" style="display:inline-block; background:#0f4c48; color:#ffffff; text-decoration:none; font-size:15px; font-weight:600; padding:12px 24px; border-radius:8px;">
                                Review in the portal
                            </a>
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