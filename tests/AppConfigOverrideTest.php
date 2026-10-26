<?php

namespace KolossusAI\ChatCodeIgniter\Tests;

use CodeIgniter\Config\Factories;
use KolossusAI\ChatCodeIgniter\Config\KolossusChat as PackageConfig;
use KolossusAI\ChatCodeIgniter\KolossusChat;

/**
 * An app's own app/Config/KolossusChat.php wins over the package's settings: the
 * package asks for config('KolossusChat') by its short name, the only lookup for which
 * CodeIgniter prefers the App version (Factories::locateClass, 4.4 to 4.7). The app
 * class is stood in with Factories::injectMock under that short name.
 */
final class AppConfigOverrideTest extends TestCase
{
    public function test_app_config_extending_the_package_is_used(): void
    {
        $app = new class () extends PackageConfig {
            public bool $sitewide = false;

            public array $excludePaths = ['admin/*'];

            public string $theme = 'dark';
        };
        Factories::injectMock('config', 'KolossusChat', $app);

        $this->assertSame($app, KolossusChat::settings());
        $cfg = service('kolossusChat')->config();
        $this->assertFalse($cfg->sitewide);
        $this->assertSame(['admin/*'], $cfg->excludePaths);
        $this->assertSame('dark', $cfg->theme);
    }

    public function test_env_still_applies_to_what_the_app_class_does_not_set(): void
    {
        $this->setEnv(['KOLOSSUS_CHAT_WIDGET_TOKEN' => self::TOKEN]);
        $app = new class () extends PackageConfig {
            public string $theme = 'dark';
        };
        Factories::injectMock('config', 'KolossusChat', $app);

        $cfg = service('kolossusChat')->config();
        $this->assertSame(self::TOKEN, $cfg->widgetToken);
        $this->assertSame('dark', $cfg->theme);
    }

    public function test_app_config_not_extending_the_package_falls_back(): void
    {
        Factories::injectMock('config', 'KolossusChat', new class () {
            public bool $sitewide = false;
        });

        $this->assertSame(PackageConfig::class, get_class(KolossusChat::settings()));
        $this->assertTrue(service('kolossusChat')->config()->sitewide);
    }

    public function test_no_app_config_uses_the_package_settings(): void
    {
        $this->assertSame(PackageConfig::class, get_class(KolossusChat::settings()));
    }
}
