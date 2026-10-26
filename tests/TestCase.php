<?php

namespace KolossusAI\ChatCodeIgniter\Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Sets and clears the KOLOSSUS_CHAT_* environment values the package reads (env()
 * looks in $_ENV, $_SERVER and getenv()). Configs are rebuilt for every test
 * (CIUnitTestCase resets the factories), so each test sees its own values.
 */
abstract class TestCase extends CIUnitTestCase
{
    protected const TOKEN = 'wt_abcdefgh12345678';

    protected const SECRET = 'ws_secret12345678';

    protected const HOST = 'https://chat.example.com';

    private const KEYS = [
        'KOLOSSUS_CHAT_HOST_URL', 'KOLOSSUS_CHAT_WIDGET_TOKEN', 'KOLOSSUS_CHAT_WIDGET_SECRET',
        'KOLOSSUS_CHAT_SITEWIDE', 'KOLOSSUS_CHAT_THEME', 'KOLOSSUS_CHAT_PRIMARY_COLOR',
        'KOLOSSUS_CHAT_LAYOUT', 'KOLOSSUS_CHAT_OPEN_ON_LOAD', 'KOLOSSUS_CHAT_POSITION',
        'KOLOSSUS_CHAT_OFFSET_X', 'KOLOSSUS_CHAT_OFFSET_Y', 'KOLOSSUS_CHAT_BUBBLE_SIZE',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearEnv();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
        parent::tearDown();
    }

    /** @param array<string, string> $values */
    protected function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function clearEnv(): void
    {
        foreach (self::KEYS as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function scriptTag(string $host = self::HOST): string
    {
        return '<script src="'.$host.'/static/widget/widget.js" async></script>';
    }
}
