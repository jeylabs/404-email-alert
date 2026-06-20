<?php

namespace Jeylabs\PageNotFoundEmailAlert\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Jeylabs\PageNotFoundEmailAlert\Http\Middleware\EnsureAllowedGoogleUser;

class AuthController
{
    /**
     * Show the login screen (Google sign-in button + optional reCAPTCHA).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        $config = $this->config();

        if ($this->currentEmail($request) && EnsureAllowedGoogleUser::isAllowed($this->currentEmail($request), $config['auth'])) {
            return redirect($this->intended($request));
        }

        return view('page-not-found-email-alert::login', [
            'recaptcha'        => $config['recaptcha'],
            'googleConfigured' => $this->googleConfigured($config['auth']),
            'error'            => $request->session()->get('pnf_auth_error'),
        ]);
    }

    /**
     * Verify the reCAPTCHA, then redirect the visitor to Google's consent
     * screen. This is the only public, unauthenticated entry point, so the
     * bot check lives here.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request)
    {
        $config = $this->config();

        if (! $this->googleConfigured($config['auth'])) {
            return $this->fail($request, 'Google login is not configured.');
        }

        if (! $this->verifyCaptcha($request, $config['recaptcha'])) {
            return $this->fail($request, 'Captcha verification failed. Please try again.');
        }

        $state = Str::random(40);
        $request->session()->put('pnf_oauth_state', $state);

        $query = http_build_query([
            'client_id'     => $config['auth']['google']['client_id'],
            'redirect_uri'  => $this->callbackUrl($config['auth']),
            'response_type' => 'code',
            'scope'         => 'openid email',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    /**
     * Handle the OAuth callback: exchange the code, read the verified email and
     * grant access only if it is on the allow-list.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        $config = $this->config();

        if ($request->query('state') !== $request->session()->pull('pnf_oauth_state')) {
            return $this->fail($request, 'Invalid authentication state. Please try again.');
        }

        if (! $request->filled('code')) {
            return $this->fail($request, 'Google did not return an authorization code.');
        }

        $token = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code'          => $request->query('code'),
            'client_id'     => $config['auth']['google']['client_id'],
            'client_secret' => $config['auth']['google']['client_secret'],
            'redirect_uri'  => $this->callbackUrl($config['auth']),
            'grant_type'    => 'authorization_code',
        ]);

        if (! $token->successful() || ! $token->json('access_token')) {
            return $this->fail($request, 'Could not complete sign-in with Google.');
        }

        $profile = Http::withToken($token->json('access_token'))
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        $email = $profile->json('email');
        $verified = $profile->json('email_verified');

        if (! $email || $verified === false) {
            return $this->fail($request, 'Your Google email could not be verified.');
        }

        if (! EnsureAllowedGoogleUser::isAllowed($email, $config['auth'])) {
            return $this->fail($request, 'The account '.$email.' is not permitted to view this page.');
        }

        $request->session()->regenerate();
        $request->session()->put(EnsureAllowedGoogleUser::SESSION_KEY, $email);

        return redirect($this->intended($request));
    }

    /**
     * Sign the visitor out.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        $request->session()->forget(EnsureAllowedGoogleUser::SESSION_KEY);

        return redirect()->route('page-not-found.login');
    }

    /**
     * Verify a reCAPTCHA response with Google. Returns true when verification
     * is disabled.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $config
     * @return bool
     */
    protected function verifyCaptcha(Request $request, array $config)
    {
        if (! ($config['enabled'] ?? false)) {
            return true;
        }

        $response = $request->input('g-recaptcha-response', $request->input('recaptcha_token'));

        if (empty($response) || empty($config['secret_key'])) {
            return false;
        }

        $result = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $config['secret_key'],
            'response' => $response,
            'remoteip' => $request->ip(),
        ])->json();

        if (! ($result['success'] ?? false)) {
            return false;
        }

        // reCAPTCHA v3 returns a score; enforce the configured threshold.
        if (array_key_exists('score', (array) $result)) {
            return (float) $result['score'] >= (float) ($config['min_score'] ?? 0.5);
        }

        return true;
    }

    /**
     * Whether Google OAuth credentials are present.
     *
     * @param  array  $auth
     * @return bool
     */
    protected function googleConfigured(array $auth)
    {
        return ! empty($auth['google']['client_id']) && ! empty($auth['google']['client_secret']);
    }

    /**
     * The OAuth callback URL (overridable via config for proxied setups).
     *
     * @param  array  $auth
     * @return string
     */
    protected function callbackUrl(array $auth)
    {
        return $auth['google']['redirect'] ?: route('page-not-found.auth.callback');
    }

    /**
     * Resolve the URL to send the visitor to after a successful login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function intended(Request $request)
    {
        $intended = $request->session()->pull('pnf_auth_intended');

        if ($intended) {
            return $intended;
        }

        return \Illuminate\Support\Facades\Route::has('page-not-found.dashboard')
            ? route('page-not-found.dashboard')
            : url('/');
    }

    /**
     * The currently signed-in email, if any.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function currentEmail(Request $request)
    {
        return $request->session()->get(EnsureAllowedGoogleUser::SESSION_KEY);
    }

    /**
     * Redirect back to the login screen with an error message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $message
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function fail(Request $request, $message)
    {
        $request->session()->flash('pnf_auth_error', $message);

        return redirect()->route('page-not-found.login');
    }

    /**
     * Resolve the package configuration, with the auth/recaptcha sub-arrays.
     *
     * @return array
     */
    protected function config()
    {
        return [
            'auth'      => (array) config('page-not-found-email-alert.auth', []),
            'recaptcha' => (array) config('page-not-found-email-alert.recaptcha', []),
        ];
    }
}
