<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your account details</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family: Arial, Helvetica, sans-serif; color:#1f2933;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#111827; padding:24px 32px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">Project Management System</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:16px; margin:0 0 16px;">Dear {{ $name }},</p>

                            <p style="font-size:14px; line-height:1.6; margin:0 0 16px;">
                                Welcome aboard. An account has been created for you on the Nanosoft Project Management System.
                                You can use the credentials below to sign in.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; margin:0 0 20px;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:14px;">
                                        <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $email }}</p>
                                        <p style="margin:0;"><strong>Temporary password:</strong> {{ $password }}</p>
                                    </td>
                                </tr>
                            </table>

                            @if ($loginUrl)
                                <p style="text-align:center; margin:0 0 24px;">
                                    <a href="{{ $loginUrl }}" style="background-color:#2563eb; color:#ffffff; text-decoration:none; padding:10px 24px; border-radius:6px; font-size:14px; display:inline-block;">
                                        Log in to your account
                                    </a>
                                </p>
                            @endif

                            <p style="font-size:13px; line-height:1.6; color:#6b7280; margin:0 0 8px;">
                                For security reasons, please log in and change this password as soon as possible.
                            </p>

                            <p style="font-size:13px; line-height:1.6; color:#6b7280; margin:0;">
                                If you were not expecting this email, please contact your administrator.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f9fafb; padding:16px 32px; font-size:12px; color:#9ca3af;">
                            &copy; {{ date('Y') }} Nanosoft Project Management System. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
