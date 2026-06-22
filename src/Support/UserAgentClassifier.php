<?php

namespace Jeylabs\PageNotFoundEmailAlert\Support;

/**
 * Best-effort classification of a request's user agent as an automated client
 * (bot/crawler/scanner/tool) versus a human browser, so scanner noise can be
 * separated from real broken links.
 */
class UserAgentClassifier
{
    /**
     * Substrings that mark a user agent as automated. Lower-cased.
     *
     * @var array<int, string>
     */
    protected static $signatures = [
        // Generic crawlers
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'adsbot', 'feedfetcher',
        // Search / social
        'bing', 'google', 'yandex', 'baidu', 'duckduck', 'sogou', 'exabot',
        'facebook', 'twitter', 'telegram', 'whatsapp', 'embedly', 'pinterest',
        'redditbot', 'linkedinbot', 'slackbot', 'discordbot', 'quora',
        // SEO / marketing
        'ahrefs', 'semrush', 'mj12', 'dotbot', 'majestic', 'seznam', 'dataforseo',
        'petalbot', 'bytespider', 'gptbot', 'ccbot', 'claudebot', 'amazonbot',
        // HTTP clients / libraries
        'curl', 'wget', 'python-requests', 'python-urllib', 'scrapy', 'httpclient',
        'http-client', 'http_request', 'libwww', 'okhttp', 'go-http-client', 'java/',
        'apache-httpclient', 'axios', 'guzzle', 'node-fetch', 'postmanruntime',
        'restsharp', 'aiohttp', 'httpx', 'lwp::',
        // Headless / automation
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        // Security scanners
        'masscan', 'nmap', 'nikto', 'sqlmap', 'zgrab', 'nuclei', 'censys', 'shodan',
        'wpscan', 'dirbuster', 'gobuster', 'fuzz', 'acunetix', 'nessus', 'openvas',
        'netsparker', 'zaproxy', 'l9scan', 'expanse',
    ];

    /**
     * Determine whether the given user agent looks automated.
     *
     * A blank user agent is treated as a bot — well-behaved browsers always
     * send one, so its absence usually signals a scanner or scripted client.
     *
     * @param  string|null  $userAgent
     * @return bool
     */
    public static function isBot($userAgent)
    {
        $ua = strtolower(trim((string) $userAgent));

        if ($ua === '') {
            return true;
        }

        foreach (static::signatures() as $needle) {
            if ($needle !== '' && str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The signature list, including any extra substrings configured by the app.
     *
     * @return array<int, string>
     */
    protected static function signatures()
    {
        $extra = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            (array) config('page-not-found-email-alert.record.bot_user_agents', [])
        );

        return array_merge(static::$signatures, $extra);
    }
}
