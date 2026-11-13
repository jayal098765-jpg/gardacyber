<?php

namespace Tests\Unit;

use App\Services\BrowserClearance;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BrowserClearanceTest extends TestCase
{
    public function test_recognises_an_edge_interstitial(): void
    {
        $clearance = new BrowserClearance;

        $this->assertTrue($clearance->isChallengePage(
            '<html><head><title>Just a moment...</title></head><body>Enable JavaScript and cookies to continue</body></html>'
        ));
    }

    public function test_recognises_a_rendered_application_page(): void
    {
        $clearance = new BrowserClearance;

        $this->assertFalse($clearance->isChallengePage(
            '<html><head><title>Example Domain</title></head><body><h1>Example</h1></body></html>'
        ));
    }

    public function test_does_not_treat_an_empty_document_as_a_challenge(): void
    {
        $this->assertFalse((new BrowserClearance)->isChallengePage('   '));
    }

    public function test_does_not_treat_a_cleared_page_as_a_challenge(): void
    {
        // Cloudflare keeps its detection script in the document it serves once
        // the challenge has passed, so that token must not imply a challenge.
        $cleared = '<html><head><title>Protected site</title>'
            .'<script src="/cdn-cgi/challenge-platform/scripts/jsd/main.js"></script>'
            .'</head><body><h1>Welcome back</h1></body></html>';

        $this->assertFalse((new BrowserClearance)->isChallengePage($cleared));
    }

    public function test_recognises_a_hard_block_page(): void
    {
        $this->assertTrue((new BrowserClearance)->isChallengePage(
            '<html><head><title>Attention Required! | Cloudflare</title></head><body>Sorry, you have been blocked</body></html>'
        ));
    }

    public function test_keeps_only_cookies_the_scanned_host_can_use(): void
    {
        $clearance = new BrowserClearance;

        $cookies = $clearance->relevantCookies([
            ['name' => 'cf_clearance', 'value' => 'token', 'domain' => 'blocked.example.com'],
            ['name' => '__cf_bm', 'value' => 'bm', 'domain' => '.example.com'],
            ['name' => '_ga', 'value' => 'analytics', 'domain' => 'other.example.net'],
            ['name' => 'no_domain', 'value' => 'ignored', 'domain' => ''],
        ], 'blocked.example.com');

        $this->assertSame(['cf_clearance', '__cf_bm'], array_column($cookies, 'name'));
        $this->assertSame('example.com', $cookies[1]['domain']);
    }

    public function test_builds_a_cookie_header_from_the_clearance_jar(): void
    {
        $clearance = new BrowserClearance;

        $header = $clearance->cookieHeader([
            ['name' => 'cf_clearance', 'value' => 'abc', 'domain' => 'blocked.example.com'],
            ['name' => '__cf_bm', 'value' => 'def', 'domain' => 'blocked.example.com'],
            ['name' => '', 'value' => 'skipped', 'domain' => 'blocked.example.com'],
        ]);

        $this->assertSame('cf_clearance=abc; __cf_bm=def', $header);
    }

    public function test_returns_a_header_even_with_no_cookies(): void
    {
        $this->assertSame('', (new BrowserClearance)->cookieHeader([]));
    }

    public function test_does_not_launch_a_browser_when_the_handoff_is_disabled(): void
    {
        Config::set('scanner.browser_bypass', false);

        $this->assertNull((new BrowserClearance)->solve('blocked.example.com', '104.16.0.5'));
    }

    public function test_tries_a_visible_browser_after_the_headless_attempt(): void
    {
        // A managed Turnstile widget rejects a headless build, so a desktop
        // session gets a second, visible attempt.
        $this->assertSame([true, false], (new BrowserClearance)->attemptModes());
    }

    public function test_skips_the_visible_attempt_when_the_fallback_is_disabled(): void
    {
        Config::set('scanner.browser_headful_fallback', false);

        $this->assertSame([true], (new BrowserClearance)->attemptModes());
    }

    public function test_uses_only_a_visible_browser_when_headless_mode_is_off(): void
    {
        Config::set('scanner.browser_headless', false);

        $this->assertSame([false], (new BrowserClearance)->attemptModes());
    }

    public function test_does_not_offer_a_visible_browser_without_a_desktop_session(): void
    {
        $clearance = new class extends BrowserClearance
        {
            public function canShowWindow(): bool
            {
                return false;
            }
        };

        $this->assertSame([true], $clearance->attemptModes());
    }
}
