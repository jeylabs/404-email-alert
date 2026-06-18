<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $data['subject'] ?? '404 Page Not Found Alert' }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #333; line-height: 1.5;">
    <h2 style="margin-bottom: 4px;">404 Page Not Found</h2>
    <p style="margin-top: 0; color: #777;">A visitor reached a page that does not exist on your application.</p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
        <tr>
            <td style="font-weight: bold; width: 140px; vertical-align: top;">URL</td>
            <td style="word-break: break-all;">{{ $data['url'] }}</td>
        </tr>
        <tr style="background: #f7f7f7;">
            <td style="font-weight: bold; vertical-align: top;">Method</td>
            <td>{{ $data['method'] }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; vertical-align: top;">Referer</td>
            <td style="word-break: break-all;">{{ $data['referer'] ?: '—' }}</td>
        </tr>
        <tr style="background: #f7f7f7;">
            <td style="font-weight: bold; vertical-align: top;">IP Address</td>
            <td>{{ $data['ip'] ?: '—' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold; vertical-align: top;">User Agent</td>
            <td style="word-break: break-all;">{{ $data['user_agent'] ?: '—' }}</td>
        </tr>
        <tr style="background: #f7f7f7;">
            <td style="font-weight: bold; vertical-align: top;">Time</td>
            <td>{{ $data['timestamp'] }}</td>
        </tr>
    </table>
</body>
</html>
