<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Set Your KK Profiling Account Password</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#111827;-webkit-text-size-adjust:100%;">
@php
    $logoSrc = $logoUrl ?? null;
    if (! empty($logoPath) && is_file($logoPath) && isset($message)) {
        try {
            $logoSrc = $message->embed($logoPath);
        } catch (\Throwable $e) {
            $logoSrc = $logoUrl ?? null;
        }
    }
@endphp
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f4f6;width:100%;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                {{-- max-width only; avoid fixed width + padding that forces Gmail horizontal scroll --}}
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:12px;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
                    <tr>
                        <td align="center" style="padding:28px 24px 8px 24px;">
                            @if (!empty($logoSrc))
                                <img
                                    src="{{ $logoSrc }}"
                                    alt="SK OnePortal"
                                    width="88"
                                    height="88"
                                    style="display:block;width:88px;max-width:88px;height:auto;margin:0 auto 12px auto;border:0;outline:none;text-decoration:none;"
                                >
                            @endif
                            <p style="margin:0;font-size:20px;font-weight:700;color:#0f172a;line-height:1.3;">SK OnePortal</p>
                            <p style="margin:6px 0 0 0;font-size:13px;color:#64748b;line-height:1.4;">Kabataan · KK Profiling</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 24px 8px 24px;">
                            <p style="margin:0 0 14px 0;font-size:16px;line-height:1.5;color:#111827;">Hello!</p>
                            <p style="margin:0 0 14px 0;font-size:15px;line-height:1.6;color:#334155;">
                                Thank you for submitting your KK Profiling registration.
                            </p>
                            <p style="margin:0 0 8px 0;font-size:15px;line-height:1.6;color:#334155;">
                                Click the button below to verify your email and set your account password.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:16px 24px 24px 24px;">
                            <a
                                href="{{ $setPasswordUrl }}"
                                target="_blank"
                                rel="noopener"
                                style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;background-color:#0450a8;line-height:1.25;mso-padding-alt:0;"
                            >
                                <!--[if mso]><i style="letter-spacing:28px;mso-font-width:-100%;mso-text-raise:21pt;">&nbsp;</i><![endif]-->
                                <span style="color:#ffffff;font-weight:600;">Set Password</span>
                                <!--[if mso]><i style="letter-spacing:28px;mso-font-width:-100%;">&nbsp;</i><![endif]-->
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 28px 24px;">
                            <p style="margin:0 0 10px 0;font-size:13px;line-height:1.6;color:#64748b;">
                                This link will expire in 24 hours for your security.
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#64748b;">
                                If you did not submit this form, no further action is required.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
