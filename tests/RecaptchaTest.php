<?php

namespace Jeylabs\PageNotFoundEmailAlert\Tests;

use Illuminate\Support\Facades\Http;

class RecaptchaTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('page-not-found-email-alert.dashboard.enabled', true);

        $app['config']->set('page-not-found-email-alert.auth.enabled', true);
        $app['config']->set('page-not-found-email-alert.auth.allowed_emails', ['boss@example.com']);
        $app['config']->set('page-not-found-email-alert.auth.google.client_id', 'client-id');
        $app['config']->set('page-not-found-email-alert.auth.google.client_secret', 'client-secret');

        $app['config']->set('page-not-found-email-alert.recaptcha.enabled', true);
        $app['config']->set('page-not-found-email-alert.recaptcha.site_key', 'site-key');
        $app['config']->set('page-not-found-email-alert.recaptcha.secret_key', 'secret-key');
    }

    public function test_login_page_includes_the_recaptcha_widget()
    {
        $this->get(route('page-not-found.login'))
            ->assertOk()
            ->assertSee('g-recaptcha', false)
            ->assertSee('site-key');
    }

    public function test_redirect_is_blocked_without_a_valid_captcha()
    {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $this->post(route('page-not-found.auth.redirect'), ['g-recaptcha-response' => 'bad'])
            ->assertRedirect(route('page-not-found.login'));

        $this->assertNull(session('pnf_oauth_state'));
    }

    public function test_redirect_proceeds_with_a_valid_captcha()
    {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $this->post(route('page-not-found.auth.redirect'), ['g-recaptcha-response' => 'good'])
            ->assertRedirectContains('accounts.google.com');

        $this->assertNotNull(session('pnf_oauth_state'));
    }

    public function test_v3_score_below_threshold_is_rejected()
    {
        config()->set('page-not-found-email-alert.recaptcha.min_score', 0.7);

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.3]),
        ]);

        $this->post(route('page-not-found.auth.redirect'), ['g-recaptcha-response' => 'lowscore'])
            ->assertRedirect(route('page-not-found.login'));
    }
}
