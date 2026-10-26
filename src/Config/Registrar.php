<?php

namespace KolossusAI\ChatCodeIgniter\Config;

use KolossusAI\ChatCodeIgniter\Filters\InjectChatWidget;

/**
 * Found by CodeIgniter's auto-discovery (Config\Modules: registrars), so
 * `composer require` is enough to load it.
 *
 * It only adds the filter's alias. Turning it on for every page happens in
 * Config/Events.php: a registrar's arrays are merged shallowly, so returning
 * 'globals' here would replace the app's whole globals['after'] list.
 */
class Registrar
{
    public static function Filters(): array
    {
        return [
            'aliases' => [
                InjectChatWidget::ALIAS => InjectChatWidget::class,
            ],
        ];
    }
}
