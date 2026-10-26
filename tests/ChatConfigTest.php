<?php

namespace KolossusAI\ChatCodeIgniter\Tests;

use KolossusAI\ChatCodeIgniter\ChatConfig;
use KolossusAI\ChatCodeIgniter\Config\KolossusChat;

final class ChatConfigTest extends TestCase
{
    private function cfg(): ChatConfig
    {
        return ChatConfig::fromConfig(new KolossusChat());
    }

    public function test_nothing_set_is_the_free_assistant_on_the_cloud(): void
    {
        $cfg = $this->cfg();
        $this->assertTrue($cfg->configured());
        $this->assertSame('free-codeigniter', $cfg->widgetToken);
        $this->assertSame('https://engine.kolossus.ai', $cfg->hostUrl);
        $this->assertSame([], $cfg->problems);
        $this->assertTrue($cfg->sitewide);
        $this->assertSame('remember', $cfg->openOnLoad);
    }

    public function test_values_from_env(): void
    {
        $this->setEnv([
            'KOLOSSUS_CHAT_WIDGET_TOKEN' => self::TOKEN,
            'KOLOSSUS_CHAT_HOST_URL' => 'http://localhost:8005/',
            'KOLOSSUS_CHAT_THEME' => 'Dark',
            'KOLOSSUS_CHAT_PRIMARY_COLOR' => '#ABC',
            'KOLOSSUS_CHAT_POSITION' => 'left',
            'KOLOSSUS_CHAT_OFFSET_X' => '040',
            'KOLOSSUS_CHAT_BUBBLE_SIZE' => 'large',
        ]);
        $cfg = $this->cfg();
        $this->assertSame(self::TOKEN, $cfg->widgetToken);
        $this->assertSame('http://localhost:8005', $cfg->hostUrl);
        $this->assertSame('dark', $cfg->theme);
        $this->assertSame('#aabbcc', $cfg->primaryColor);
        $this->assertSame('left', $cfg->position);
        $this->assertSame('40', $cfg->offsetX);
        $this->assertSame('large', $cfg->bubbleSize);
        $this->assertSame([], $cfg->problems);
    }

    public function test_blank_env_values_keep_the_defaults(): void
    {
        $this->setEnv(['KOLOSSUS_CHAT_WIDGET_TOKEN' => '  ', 'KOLOSSUS_CHAT_HOST_URL' => '']);
        $cfg = $this->cfg();
        $this->assertSame('free-codeigniter', $cfg->widgetToken);
        $this->assertSame('https://engine.kolossus.ai', $cfg->hostUrl);
    }

    public function test_bad_values_are_dropped_with_a_problem(): void
    {
        $this->setEnv([
            'KOLOSSUS_CHAT_WIDGET_TOKEN' => 'wt_bad token!',
            'KOLOSSUS_CHAT_THEME' => 'purple',
            'KOLOSSUS_CHAT_OFFSET_Y' => '5000',
        ]);
        $cfg = $this->cfg();
        $this->assertFalse($cfg->configured());
        $this->assertSame('', $cfg->theme);
        $this->assertSame('', $cfg->offsetY);
        $this->assertCount(3, $cfg->problems);
    }

    public function test_the_secret_in_the_token_field_is_refused(): void
    {
        $this->setEnv(['KOLOSSUS_CHAT_WIDGET_TOKEN' => self::SECRET]);
        $cfg = $this->cfg();
        $this->assertSame('', $cfg->widgetToken);
        $this->assertStringContainsString('KOLOSSUS_CHAT_WIDGET_SECRET', $cfg->problems[0]);
    }

    public function test_free_assistant_never_gets_the_secret(): void
    {
        $this->setEnv(['KOLOSSUS_CHAT_WIDGET_SECRET' => self::SECRET]);
        $this->assertSame('', $this->cfg()->widgetSecret);
        $this->setEnv(['KOLOSSUS_CHAT_WIDGET_TOKEN' => self::TOKEN]);
        $this->assertSame(self::SECRET, $this->cfg()->widgetSecret);
    }

    public function test_host_rules(): void
    {
        foreach (['http://example.com', 'ftp://x.com', 'https://bad host.com', 'javascript:alert(1)'] as $bad) {
            $this->setEnv(['KOLOSSUS_CHAT_HOST_URL' => $bad]);
            $cfg = $this->cfg();
            $this->assertSame('', $cfg->hostUrl, $bad);
            $this->assertFalse($cfg->configured(), $bad);
        }
        $this->assertSame('https://chat.example.com:8443', ChatConfig::cleanHostUrl('https://Chat.Example.com:8443/x?y'));
        $this->assertSame('http://[::1]:8005', ChatConfig::cleanHostUrl('http://[::1]:8005'));
    }

    public function test_sitewide_switch(): void
    {
        foreach (['false', '0', 'no', 'off'] as $off) {
            $this->setEnv(['KOLOSSUS_CHAT_SITEWIDE' => $off]);
            $this->assertFalse($this->cfg()->sitewide, $off);
        }
        $this->setEnv(['KOLOSSUS_CHAT_SITEWIDE' => 'true']);
        $this->assertTrue($this->cfg()->sitewide);
    }

    public function test_exclude_paths(): void
    {
        $cfg = ChatConfig::fromArray(['exclude_paths' => ['/admin/*', 'login']]);
        $this->assertSame(['admin/*', 'login'], $cfg->excludePaths);
        $this->assertTrue($cfg->excludes('/admin/users'));
        $this->assertTrue($cfg->excludes('login'));
        $this->assertTrue($cfg->excludes('LOGIN/'));
        $this->assertFalse($cfg->excludes('login/help'));
        $this->assertFalse($cfg->excludes('administrator'));
        $this->assertFalse($cfg->excludes(''));
        $bad = ChatConfig::fromArray(['exclude_paths' => [5]]);
        $this->assertSame([], $bad->excludePaths);
        $this->assertCount(1, $bad->problems);
    }
}
