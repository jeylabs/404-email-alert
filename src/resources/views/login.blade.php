<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign in — Not So Great Requests</title>
    @if (! empty($recaptcha['enabled']) && ! empty($recaptcha['site_key']))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f5f7; color: #1f2430;
        }
        .box {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 32px;
            width: 100%; max-width: 380px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.05);
        }
        h1 { font-size: 18px; margin: 0 0 6px; }
        p.muted { color: #6b7280; font-size: 14px; margin: 0 0 22px; }
        .error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 8px;
                 padding: 10px 12px; font-size: 13px; margin-bottom: 18px; text-align: left; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
            padding: 11px 16px; border-radius: 8px; border: 1px solid #d1d5db; background: #fff;
            color: #1f2430; font-size: 15px; font-weight: 600; cursor: pointer;
        }
        .btn:hover { background: #f9fafb; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .g-recaptcha { display: inline-block; margin-bottom: 18px; }
        .note { color: #9ca3af; font-size: 12px; margin-top: 18px; }
    </style>
</head>
<body>
<div class="box">
    <h1>Not So Great Requests</h1>
    <p class="muted">Sign in with an authorised Google account to continue.</p>

    @if (! empty($error))
        <div class="error">{{ $error }}</div>
    @endif

    @if (! $googleConfigured)
        <div class="error">Google login is not configured. Set <code>PAGE_NOT_FOUND_GOOGLE_CLIENT_ID</code> and <code>PAGE_NOT_FOUND_GOOGLE_CLIENT_SECRET</code>.</div>
    @else
        <form method="POST" action="{{ route('page-not-found.auth.redirect') }}">
            @csrf

            @if (! empty($recaptcha['enabled']) && ! empty($recaptcha['site_key']))
                <div class="g-recaptcha" data-sitekey="{{ $recaptcha['site_key'] }}"></div>
            @endif

            <button type="submit" class="btn">
                <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.71-1.57 2.68-3.9 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.72A5.4 5.4 0 0 1 3.68 9c0-.6.1-1.18.29-1.72V4.95H.96A9 9 0 0 0 0 9c0 1.45.35 2.82.96 4.05l3.01-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58A9 9 0 0 0 9 0 9 9 0 0 0 .96 4.95L3.97 7.3C4.68 5.16 6.66 3.58 9 3.58z"/></svg>
                Continue with Google
            </button>
        </form>
    @endif

    <p class="note">Access is restricted to configured email addresses.</p>
</div>
</body>
</html>
