<!doctype html>
<html lang="en">
<body style="margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#0b2341;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:560px;background:#ffffff;border:1px solid #dbe2ec;border-radius:10px;">
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 8px;color:#b7791f;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">PaperTrail Security</p>
                            <h1 style="margin:0 0 16px;font-size:22px;">Reset your PaperTrail password</h1>
                            <p style="margin:0 0 14px;line-height:1.6;">Hello {{ $user->name }},</p>
                            <p style="margin:0 0 22px;line-height:1.6;">Use the secure link below to reset your password. This link expires according to the PaperTrail password reset policy.</p>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $resetUrl }}" style="display:inline-block;background:#f0b429;color:#0b2341;padding:12px 18px;border-radius:8px;font-weight:700;text-decoration:none;">Reset Password</a>
                            </p>
                            <p style="margin:0;color:#526173;font-size:13px;line-height:1.6;">If you did not request this reset, please contact the system administrator.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
