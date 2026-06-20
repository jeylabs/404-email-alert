<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Not So Great Requests Report</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #333; line-height: 1.5;">
    <h2 style="margin-bottom: 4px;">Not So Great Requests Report</h2>
    <p style="margin-top: 0; color: #777;">
        Summary of failed (4xx / 5xx) requests from
        <strong>{{ $report['from'] }}</strong> to <strong>{{ $report['to'] }}</strong>.
    </p>

    @php
        $cell = 'padding: 6px; border-bottom: 1px solid #eee; vertical-align: top;';
        $head = 'padding: 6px; text-align: left; border-bottom: 2px solid #ddd;';
    @endphp

    <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 680px; margin-bottom: 24px;">
        <tr>
            <td style="font-weight: bold; width: 220px;">Total failed requests</td>
            <td>{{ number_format($report['total']) }}</td>
        </tr>
        <tr style="background: #f7f7f7;">
            <td style="font-weight: bold;">Client errors (4xx)</td>
            <td>{{ number_format($report['client_errors']) }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Server errors (5xx)</td>
            <td>{{ number_format($report['server_errors']) }}</td>
        </tr>
    </table>

    @if ($report['total'] === 0)
        <p style="color: #2e7d32;">No failed requests were recorded during this period. 🎉</p>
    @else
        <h3 style="margin-bottom: 4px;">By status code</h3>
        <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 680px; margin-bottom: 24px;">
            <tr>
                <th style="{{ $head }}">Status</th>
                <th style="{{ $head }}">Count</th>
            </tr>
            @foreach ($report['by_status'] as $row)
                <tr>
                    <td style="{{ $cell }}">{{ $row['status'] }}</td>
                    <td style="{{ $cell }}">{{ number_format($row['count']) }}</td>
                </tr>
            @endforeach
        </table>

        <h3 style="margin-bottom: 4px;">Top paths</h3>
        <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 680px; margin-bottom: 24px;">
            <tr>
                <th style="{{ $head }}">Path</th>
                <th style="{{ $head }}">Count</th>
                <th style="{{ $head }}">Last seen</th>
            </tr>
            @foreach ($report['top_paths'] as $row)
                <tr>
                    <td style="{{ $cell }} word-break: break-all;">{{ $row['path'] }}</td>
                    <td style="{{ $cell }}">{{ number_format($row['count']) }}</td>
                    <td style="{{ $cell }}">{{ $row['last_seen'] }}</td>
                </tr>
            @endforeach
        </table>

        @if (! empty($report['top_ips']))
            <h3 style="margin-bottom: 4px;">Top IP addresses</h3>
            <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 680px; margin-bottom: 24px;">
                <tr>
                    <th style="{{ $head }}">IP</th>
                    <th style="{{ $head }}">Count</th>
                </tr>
                @foreach ($report['top_ips'] as $row)
                    <tr>
                        <td style="{{ $cell }}">{{ $row['ip'] }}</td>
                        <td style="{{ $cell }}">{{ number_format($row['count']) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        @if (! empty($report['top_user_agents']))
            <h3 style="margin-bottom: 4px;">Top user agents</h3>
            <table cellpadding="0" cellspacing="0" border="0" style="border-collapse: collapse; width: 100%; max-width: 680px;">
                <tr>
                    <th style="{{ $head }}">User agent</th>
                    <th style="{{ $head }}">Count</th>
                </tr>
                @foreach ($report['top_user_agents'] as $row)
                    <tr>
                        <td style="{{ $cell }} word-break: break-all;">{{ $row['user_agent'] ?: '—' }}</td>
                        <td style="{{ $cell }}">{{ number_format($row['count']) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endif
</body>
</html>
