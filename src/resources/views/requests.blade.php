<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Requests — Not So Great Requests</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f5f7; color: #1f2430; line-height: 1.5; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 32px 20px 64px; }
        a { color: #2563eb; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; margin: 0 0 20px; font-size: 14px; }
        form.filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 20px; }
        form.filters label { display: flex; flex-direction: column; font-size: 12px; color: #6b7280; gap: 4px; }
        form.filters input, form.filters select { padding: 7px 9px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 14px; background: #fff; color: inherit; }
        form.filters .btn { padding: 8px 14px; border: 0; border-radius: 7px; background: #2563eb; color: #fff; font-size: 14px; cursor: pointer; }
        form.filters .clear { align-self: center; font-size: 13px; }
        section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px 14px; border-bottom: 1px solid #f0f1f3; font-size: 13px; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
        tr:last-child td { border-bottom: 0; }
        code { word-break: break-all; }
        .pill { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill.c4 { background: #fef3c7; color: #92400e; }
        .pill.c5 { background: #fee2e2; color: #991b1b; }
        .tag { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 11px; background: #eef2ff; color: #3730a3; }
        .tag.bot { background: #f3f4f6; color: #4b5563; }
        .empty { padding: 40px 20px; text-align: center; color: #6b7280; }
        .pager { display: flex; justify-content: space-between; align-items: center; padding: 14px; font-size: 14px; color: #6b7280; }
        .pager a { padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 7px; text-decoration: none; }
        .pager span.disabled { opacity: .4; }
    </style>
</head>
<body>
<div class="wrap">
    <p class="muted" style="margin-bottom: 6px;"><a href="{{ route('page-not-found.dashboard', ['hours' => $filters['hours']]) }}">← Back to dashboard</a></p>
    <h1>Requests</h1>
    <p class="muted">{{ $window['from'] }} → {{ $window['to'] }} · {{ number_format($rows->total()) }} matching record(s)</p>

    <form method="GET" action="{{ route('page-not-found.requests') }}" class="filters">
        <label>Search path
            <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="e.g. wp-admin">
        </label>
        <label>Exact path
            <input type="text" name="path" value="{{ $filters['path'] }}">
        </label>
        <label>Status
            <input type="number" name="status" value="{{ $filters['status'] }}" placeholder="any" style="width: 90px;">
        </label>
        <label>Client
            <select name="bot">
                <option value="" @selected($filters['bot'] === '')>Any</option>
                <option value="0" @selected($filters['bot'] === '0')>Humans</option>
                <option value="1" @selected($filters['bot'] === '1')>Bots</option>
            </select>
        </label>
        <label>Window (hours)
            <input type="number" name="hours" value="{{ $filters['hours'] }}" style="width: 90px;">
        </label>
        <button type="submit" class="btn">Filter</button>
        <a class="clear" href="{{ route('page-not-found.requests', ['hours' => $filters['hours']]) }}">Clear</a>
    </form>

    <section>
        @if ($rows->isEmpty())
            <div class="empty">No requests match these filters.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Path</th>
                        <th>IP</th>
                        <th>Referer</th>
                        <th>Client</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->created_at }}</td>
                        <td><span class="pill {{ $row->status_code >= 500 ? 'c5' : 'c4' }}">{{ $row->status_code }}</span></td>
                        <td>{{ $row->method }}</td>
                        <td><code>{{ $row->path }}</code></td>
                        <td>{{ $row->ip ?: '—' }}</td>
                        <td>
                            @if ($row->referer)
                                <code>{{ \Illuminate\Support\Str::limit($row->referer, 60) }}</code>
                                <span class="tag">{{ $row->referer_internal ? 'internal' : 'external' }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="tag {{ $row->is_bot ? 'bot' : '' }}">{{ $row->is_bot ? 'bot' : 'human' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pager">
                @if ($rows->onFirstPage())
                    <span class="disabled">← Previous</span>
                @else
                    <a href="{{ $rows->previousPageUrl() }}">← Previous</a>
                @endif
                <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
                @if ($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}">Next →</a>
                @else
                    <span class="disabled">Next →</span>
                @endif
            </div>
        @endif
    </section>
</div>
</body>
</html>
