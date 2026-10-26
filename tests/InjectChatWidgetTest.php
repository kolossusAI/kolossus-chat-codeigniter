<?php

namespace KolossusAI\ChatCodeIgniter\Tests;

use CodeIgniter\Test\FeatureTestTrait;
use KolossusAI\ChatCodeIgniter\Filters\InjectChatWidget;

/**
 * Real requests through CodeIgniter (router, filters, response): the package's
 * Config/Events.php and Registrar are found by auto-discovery as in a real app.
 */
final class InjectChatWidgetTest extends TestCase
{
    use FeatureTestTrait;

    private const PAGE = "<!doctype html>\n<html><head><title>Shop</title></head>\n<body><h1>Hello</h1>\n</body>\n</html>";

    protected function setUp(): void
    {
        parent::setUp();
        // A fresh response (and request) per test, as a real app gets per request:
        // routes below change the shared response (404, text/plain), which would
        // otherwise carry into the next test.
        $this->resetServices();
        $page = self::PAGE;
        $this->withRoutes([
            ['get', '/', static fn () => $page],
            ['get', 'admin/users', static fn () => $page],
            ['get', 'json', static fn () => service('response')->setJSON(['ok' => true])],
            ['get', 'plain', static fn () => service('response')->setContentType('text/plain')->setBody('</body>')],
            ['get', 'download', static fn () => service('response')->download('page.html', $page)],
            ['get', 'missing', static fn () => service('response')->setStatusCode(404)->setBody($page)],
            ['get', 'redirect', static fn () => redirect()->to('/')],
            ['get', 'twice', static fn () => '<body>a</body><body>b</BODY >'],
            ['get', 'nobody', static fn () => '<p>fragment</p>'],
            ['get', 'own', static fn () => '<html><body><div data-kolossus="wt_ownownown1"></div></body></html>'],
            ['get', 'helper', static function () use ($page) {
                helper('kolossus_chat');

                return str_replace('</h1>', '</h1>'.kolossus_chat_button('Ask us', 'btn btn-primary'), $page);
            }],
        ]);
    }

    public function test_free_assistant_on_every_page_with_nothing_set(): void
    {
        $body = (string) $this->get('/')->response()->getBody();
        $this->assertStringContainsString(
            $this->scriptTag('https://engine.kolossus.ai').'<div data-kolossus="free-codeigniter" data-kolossus-open="remember" hidden aria-hidden="true"></div></body>',
            $body
        );
        $this->assertSame(1, substr_count($body, 'widget.js'));
        $this->assertStringEndsWith("</body>\n</html>", $body);
    }

    public function test_token_and_look_from_env(): void
    {
        $this->setEnv([
            'KOLOSSUS_CHAT_WIDGET_TOKEN' => self::TOKEN,
            'KOLOSSUS_CHAT_HOST_URL' => self::HOST,
            'KOLOSSUS_CHAT_THEME' => 'dark',
            'KOLOSSUS_CHAT_POSITION' => 'left',
        ]);
        $body = (string) $this->get('/')->response()->getBody();
        $this->assertStringContainsString($this->scriptTag(), $body);
        $this->assertStringContainsString(
            '<div data-kolossus="'.self::TOKEN.'" data-kolossus-theme="dark" data-kolossus-open="remember" data-kolossus-position="left" hidden aria-hidden="true">',
            $body
        );
    }

    public function test_pages_left_alone(): void
    {
        foreach (['json', 'plain', 'download', 'missing', 'redirect', 'nobody', 'own'] as $path) {
            $body = (string) $this->get($path)->response()->getBody();
            $this->assertStringNotContainsString('widget.js', $body, $path);
        }
        $this->assertSame(1, substr_count((string) $this->get('own')->response()->getBody(), 'data-kolossus='));
    }

    public function test_ajax_and_partial_requests_left_alone(): void
    {
        foreach (['X-Requested-With' => 'XMLHttpRequest', 'HX-Request' => 'true', 'Turbo-Frame' => 'main', 'X-Inertia' => 'true'] as $name => $value) {
            $body = (string) $this->withHeaders([$name => $value])->get('/')->response()->getBody();
            $this->assertStringNotContainsString('widget.js', $body, $name);
        }
    }

    public function test_last_body_tag_case_and_spaces(): void
    {
        $body = (string) $this->get('twice')->response()->getBody();
        $this->assertSame(1, substr_count($body, 'widget.js'));
        $this->assertStringEndsWith('hidden aria-hidden="true"></div></BODY >', $body);
        $this->assertStringStartsWith('<body>a</body>', $body);
    }

    public function test_sitewide_off_and_bad_settings(): void
    {
        $this->setEnv(['KOLOSSUS_CHAT_SITEWIDE' => 'false']);
        $this->assertStringNotContainsString('widget.js', (string) $this->get('/')->response()->getBody());
        $this->setEnv(['KOLOSSUS_CHAT_SITEWIDE' => 'true', 'KOLOSSUS_CHAT_WIDGET_TOKEN' => 'wt_bad token!']);
        $this->assertStringNotContainsString('widget.js', (string) $this->get('/')->response()->getBody());
        $this->setEnv(['KOLOSSUS_CHAT_WIDGET_TOKEN' => self::TOKEN, 'KOLOSSUS_CHAT_HOST_URL' => 'http://example.com']);
        $this->assertStringNotContainsString('widget.js', (string) $this->get('/')->response()->getBody());
    }

    public function test_exclude_paths_from_an_app_config(): void
    {
        $config = config(\KolossusAI\ChatCodeIgniter\Config\KolossusChat::class);
        $config->excludePaths = ['admin/*'];
        $this->assertStringNotContainsString('widget.js', (string) $this->get('admin/users')->response()->getBody());
        $this->assertStringContainsString('widget.js', (string) $this->get('/')->response()->getBody());
    }

    public function test_helper_page_gets_one_script_and_no_second_bubble(): void
    {
        $this->setEnv(['KOLOSSUS_CHAT_WIDGET_TOKEN' => self::TOKEN, 'KOLOSSUS_CHAT_HOST_URL' => self::HOST]);
        $body = (string) $this->get('helper')->response()->getBody();
        $this->assertSame(1, substr_count($body, 'widget.js'));
        $this->assertStringContainsString(
            '<button type="button" class="kolossus-chat-button btn btn-primary" data-kolossus="'.self::TOKEN.'" data-kolossus-open="remember">Ask us</button>',
            $body
        );
        $this->assertStringNotContainsString('hidden aria-hidden="true"', $body);
    }

    public function test_csp_nonce_when_the_app_turned_csp_on(): void
    {
        config('App')->CSPEnabled = true;
        $response = $this->get('/');
        $body = (string) $response->response()->getBody();
        $this->assertMatchesRegularExpression('#<script src="https://engine\.kolossus\.ai/static/widget/widget\.js" async nonce="[A-Za-z0-9+/=]+"></script>#', $body);
        preg_match('#nonce="([^"]+)"#', $body, $m);
        // The header is written when the response is sent (ResponseTrait::send):
        // the nonce went into the page's own policy, so the browser allows the script.
        $sent = $response->response();
        $sent->getCSP()->finalize($sent);
        $this->assertStringContainsString("'nonce-{$m[1]}'", $sent->getHeaderLine('Content-Security-Policy'));
    }

    public function test_appends_to_the_app_globals_once_and_keeps_them(): void
    {
        $filters = new \Config\Filters();
        $filters->globals['after'] = ['secureheaders', 'honeypot' => ['except' => 'api/*']];
        InjectChatWidget::addToGlobals($filters);
        InjectChatWidget::addToGlobals($filters);
        $this->assertSame(['secureheaders', 'honeypot' => ['except' => 'api/*'], 'kolossusChat'], $filters->globals['after']);
        $this->assertSame(InjectChatWidget::class, $filters->aliases['kolossusChat']);

        // Listed by the app itself (with its own except rules): left as it is.
        $filters->globals['after'] = ['kolossusChat' => ['except' => 'admin/*']];
        InjectChatWidget::addToGlobals($filters);
        $this->assertSame(['kolossusChat' => ['except' => 'admin/*']], $filters->globals['after']);
    }

    public function test_app_after_filters_still_run(): void
    {
        $filters = config('Filters');
        $filters->globals['after'] = ['secureheaders'];
        $response = $this->get('/')->response();
        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
        $this->assertStringContainsString('widget.js', (string) $response->getBody());
    }
}
