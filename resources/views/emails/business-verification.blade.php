<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.name') }} - KYB Verification</title>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color: #000; padding: 20px; text-align: center;">
                <img src="https://zeenah.azurewebsites.net/images/logo/logo.png" alt="{{ config('app.name') }} Logo" style="max-width: 350px;">
            </td>
        </tr>
        <tr>
            <td style="padding: 20px;">
                <h1 style="color: #333333; font-family: Arial, sans-serif;">KYB Verification</h1>
                <p style="color: #666666; font-family: Arial, sans-serif; font-size: 16px; line-height: 24px;">
                    Dear {{ $name }},
                </p>
                <p style="color: #666666; font-family: Arial, sans-serif; font-size: 16px; line-height: 24px;">
                    Please complete the KYB (Know Your Business) process for {{ $businessName }} by clicking the button below:
                </p>
                <p style="text-align: center; margin-top: 20px;">
                    <a href="{{ $kybUrl }}" style="background-color: #333333; color: #ffffff; display: inline-block; padding: 10px 20px; text-decoration: none; font-family: Arial, sans-serif; font-size: 16px; border-radius: 4px;">
                        Complete KYB
                    </a>
                </p>
                <p style="color: #666666; font-family: Arial, sans-serif; font-size: 16px; line-height: 24px; margin-top: 20px;">
                    Thank you for your cooperation.
                </p>
                <p style="color: #666666; font-family: Arial, sans-serif; font-size: 16px; line-height: 24px;">
                    Best regards,<br>
                    {{ config('app.name') }} Team
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
