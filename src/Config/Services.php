<?php

namespace KolossusAI\ChatCodeIgniter\Config;

use CodeIgniter\Config\BaseService;
use KolossusAI\ChatCodeIgniter\KolossusChat;

/** Found by CodeIgniter's auto-discovery (Config\Modules: services). */
class Services extends BaseService
{
    /** service('kolossusChat'): builds the chat HTML (shared: one per process). */
    public static function kolossusChat(bool $getShared = true): KolossusChat
    {
        if ($getShared) {
            return static::getSharedInstance('kolossusChat');
        }

        return new KolossusChat();
    }
}
