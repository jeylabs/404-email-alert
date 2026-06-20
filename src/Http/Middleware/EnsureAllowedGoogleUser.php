<?php

namespace Jeylabs\PageNotFoundEmailAlert\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAllowedGoogleUser
{
    /**
     * The session key holding the authenticated Google email address.
     */
    const SESSION_KEY = 'pnf_auth_email';

    /**
     * Allow the request through only when the visitor has signed in with Google
     * using one of the configured allow-listed email addresses.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $config = (array) config('page-not-found-email-alert.auth', []);

        // Protection disabled — leave the routes open.
        if (! ($config['enabled'] ?? false)) {
            return $next($request);
        }

        $email = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if ($email && static::isAllowed($email, $config)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'   => 'Authentication required.',
                'login_url' => route('page-not-found.login'),
            ], 401);
        }

        if ($request->hasSession()) {
            $request->session()->put('pnf_auth_intended', $request->fullUrl());
        }

        return redirect()->route('page-not-found.login');
    }

    /**
     * Determine whether the given email is on the configured allow-list.
     *
     * @param  string  $email
     * @param  array  $config
     * @return bool
     */
    public static function isAllowed($email, array $config)
    {
        $allowed = array_filter(array_map(
            fn ($value) => strtolower(trim((string) $value)),
            (array) ($config['allowed_emails'] ?? [])
        ));

        if (empty($allowed)) {
            return false;
        }

        return in_array(strtolower(trim($email)), $allowed, true);
    }
}
