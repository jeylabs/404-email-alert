<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Facades\Http;
use Jeylabs\PageNotFoundEmailAlert\Http\Middleware\EnsureAllowedGoogleUser;

class AuthTest extends TestCase
{
    /**
     * Enable the dashboard, API and Google access control for these tests.
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('page-not-found-email-alert.dashboard.enabled', true);
        $app['config']->set('page-not-found-email-alert.api.enabled', true);

        $app['config']->set('page-not-found-email-alert.auth.enabled', true);
        $app['config']->set('page-not-found-email-alert.auth.allowed_emails', ['boss@example.com']);
        $app['config']->set('page-not-found-email-alert.auth.google.client_id', 'client-id');
        $app['config']->set('page-not-found-email-alert.auth.google.client_secret', 'client-secret');
    }

    public function test_dashboard_redirects_to_login_when_not_authenticated()
    {
        $this->get('/page-not-found')
            ->assertRedirect(route('page-not-found.login'));
    }

    public function test_api_returns_401_when_not_authenticated()
    {
        $this->getJson('/api/page-not-found')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Authentication required.');
    }

    public function test_allowed_user_can_view_dashboard()
    {
        $this->withSession([EnsureAllowedGoogleUser::SESSION_KEY => 'boss@example.com'])
            ->get('/page-not-found')
            ->assertOk()
            ->assertSee('Not So Great Requests');
    }

    public function test_disallowed_email_in_session_is_rejected()
    {
        $this->withSession([EnsureAllowedGoogleUser::SESSION_KEY => 'intruder@example.com'])
            ->get('/page-not-found')
            ->assertRedirect(route('page-not-found.login'));
    }

    public function test_login_page_renders_with_google_button()
    {
        $this->get(route('page-not-found.login'))
            ->assertOk()
            ->assertSee('Continue with Google');
    }

    public function test_redirect_sends_the_visitor_to_google()
    {
        $response = $this->post(route('page-not-found.auth.redirect'));

        $response->assertRedirectContains('accounts.google.com');
        $this->assertNotNull(session('pnf_oauth_state'));
    }

    public function test_callback_signs_in_an_allowed_google_account()
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'boss@example.com',
                'email_verified' => true,
            ]),
        ]);

        $this->withSession(['pnf_oauth_state' => 'xyz'])
            ->get(route('page-not-found.auth.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertRedirect(route('page-not-found.dashboard'));

        $this->assertSame('boss@example.com', session(EnsureAllowedGoogleUser::SESSION_KEY));
    }

    public function test_callback_rejects_an_unlisted_google_account()
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok']),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'email' => 'someone@gmail.com',
                'email_verified' => true,
            ]),
        ]);

        $this->withSession(['pnf_oauth_state' => 'xyz'])
            ->get(route('page-not-found.auth.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertRedirect(route('page-not-found.login'));

        $this->assertNull(session(EnsureAllowedGoogleUser::SESSION_KEY));
    }

    public function test_callback_rejects_a_mismatched_state()
    {
        $this->withSession(['pnf_oauth_state' => 'expected'])
            ->get(route('page-not-found.auth.callback', ['code' => 'abc', 'state' => 'forged']))
            ->assertRedirect(route('page-not-found.login'));

        $this->assertNull(session(EnsureAllowedGoogleUser::SESSION_KEY));
    }
}
