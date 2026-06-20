<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Not So Great Requests</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f5f7;
            color: #1f2430;
            line-height: 1.5;
        }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px 64px; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        .muted { color: #6b7280; margin: 0; }
        .windows { margin: 20px 0; display: flex; gap: 8px; flex-wrap: wrap; }
        .windows a {
            text-decoration: none; font-size: 13px; padding: 6px 12px; border-radius: 999px;
            background: #fff; color: #374151; border: 1px solid #e5e7eb;
        }
        .windows a.active { background: #2563eb; color: #fff; border-color: #2563eb; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin: 8px 0 28px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; }
        .card .label { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
        .card .value { font-size: 30px; font-weight: 700; margin-top: 4px; }
        .card.client .value { color: #d97706; }
        .card.server .value { color: #dc2626; }
        section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 24px; overflow: hidden; }
        section h2 { font-size: 15px; margin: 0; padding: 14px 20px; border-bottom: 1px solid #f0f1f3; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 20px; border-bottom: 1px solid #f0f1f3; font-size: 14px; vertical-align: middle; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr:last-child td { border-bottom: 0; }
        .bar-cell { width: 45%; }
        .bar { background: #eef2ff; border-radius: 6px; height: 10px; overflow: hidden; }
        .bar > span { display: block; height: 100%; background: #2563eb; }
        code { word-break: break-all; }
        .empty { padding: 40px 20px; text-align: center; color: #16a34a; }
    </style>
</head>
<body>
<div class="wrap">
    @if (! empty($authEmail) && \Illuminate\Support\Facades\Route::has('page-not-found.logout'))
        <form method="POST" action="{{ route('page-not-found.logout') }}" style="float: right; font-size: 13px; color: #6b7280;">
            @csrf
            <span style="margin-right: 8px;">{{ $authEmail }}</span>
            <button type="submit" style="background: none; border: 0; color: #2563eb; cursor: pointer; padding: 0; font-size: 13px;">Sign out</button>
        </form>
    @endif

    <h1>Not So Great Requests</h1>
    <p class="muted">Failed (4xx / 5xx) requests from <strong>{{ $report['from'] }}</strong> to <strong>{{ $report['to'] }}</strong>.</p>

    <div class="windows">
        @foreach ([1 => 'Last hour', 24 => 'Last 24h', 168 => 'Last 7d', 720 => 'Last 30d'] as $value => $label)
            <a href="?hours={{ $value }}" class="{{ (int) $hours === $value ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="cards">
        <div class="card">
            <div class="label">Total failed</div>
            <div class="value">{{ number_format($report['total']) }}</div>
        </div>
        <div class="card client">
            <div class="label">Client errors (4xx)</div>
            <div class="value">{{ number_format($report['client_errors']) }}</div>
        </div>
        <div class="card server">
            <div class="label">Server errors (5xx)</div>
            <div class="value">{{ number_format($report['server_errors']) }}</div>
        </div>
    </div>

    @if ($report['total'] === 0)
        <section><div class="empty">No failed requests recorded for this period. 🎉</div></section>
    @else
        @php $maxStatus = collect($report['by_status'])->max('count') ?: 1; @endphp
        <section>
            <h2>By status code</h2>
            <table>
                <thead><tr><th>Status</th><th class="bar-cell">Share</th><th class="num">Count</th></tr></thead>
                <tbody>
                @foreach ($report['by_status'] as $row)
                    <tr>
                        <td><strong>{{ $row['status'] }}</strong></td>
                        <td class="bar-cell"><div class="bar"><span style="width: {{ max(2, (int) round($row['count'] / $maxStatus * 100)) }}%"></span></div></td>
                        <td class="num">{{ number_format($row['count']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section>
            <h2>Top paths</h2>
            <table>
                <thead><tr><th>Path</th><th>Last seen</th><th class="num">Count</th></tr></thead>
                <tbody>
                @foreach ($report['top_paths'] as $row)
                    <tr>
                        <td><code>{{ $row['path'] }}</code></td>
                        <td>{{ $row['last_seen'] }}</td>
                        <td class="num">{{ number_format($row['count']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        @if (! empty($report['top_ips']))
            <section>
                <h2>Top IP addresses</h2>
                <table>
                    <thead><tr><th>IP</th><th class="num">Count</th></tr></thead>
                    <tbody>
                    @foreach ($report['top_ips'] as $row)
                        <tr><td>{{ $row['ip'] }}</td><td class="num">{{ number_format($row['count']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if (! empty($report['top_user_agents']))
            <section>
                <h2>Top user agents</h2>
                <table>
                    <thead><tr><th>User agent</th><th class="num">Count</th></tr></thead>
                    <tbody>
                    @foreach ($report['top_user_agents'] as $row)
                        <tr><td><code>{{ $row['user_agent'] ?: '—' }}</code></td><td class="num">{{ number_format($row['count']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    @endif
</div>
</body>
</html>
