<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Error spike detected</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #333; line-height: 1.5;">
    <h2 style="margin-bottom: 4px; color: #b91c1c;">⚠️ Error spike detected</h2>
    <p style="margin-top: 0; color: #777;">
        The rule <strong>{{ $alert['name'] }}</strong> was breached.
    </p>

    @php
        $cell = 'padding: 6px; border-bottom: 1px solid #eee; vertical-align: top;';
        if (! empty($alert['statuses'])) {
            $scope = 'status '.implode(', ', $alert['statuses']);
        } elseif (! empty($alert['max_status'])) {
            $scope = 'status '.$alert['min_status'].'–'.$alert['max_status'];
        } else {
            $scope = 'status ≥ '.($alert['min_status'] ?? 400);
        }
    @endphp

    <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 640px;">
        <tr>
            <td style="{{ $cell }} font-weight: bold; width: 160px;">Observed</td>
            <td style="{{ $cell }}"><strong style="color:#b91c1c;">{{ number_format($alert['count']) }}</strong> requests in {{ $alert['window'] }} minute(s)</td>
        </tr>
        <tr style="background: #f7f7f7;">
            <td style="{{ $cell }} font-weight: bold;">Threshold</td>
            <td style="{{ $cell }}">{{ number_format($alert['threshold']) }}</td>
        </tr>
        <tr>
            <td style="{{ $cell }} font-weight: bold;">Scope</td>
            <td style="{{ $cell }}">{{ $scope }}</td>
        </tr>
        <tr style="background: #f7f7f7;">
            <td style="{{ $cell }} font-weight: bold;">Since</td>
            <td style="{{ $cell }}">{{ $alert['since'] }}</td>
        </tr>
    </table>

    <p style="color: #999; font-size: 12px; margin-top: 18px;">
        Further alerts for this rule are suppressed during the configured cooldown.
    </p>
</body>
</html>
