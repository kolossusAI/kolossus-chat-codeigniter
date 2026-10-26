<?php

/**
 * Found by CodeIgniter's auto-discovery (Config\Modules: events).
 *
 * pre_system runs before the filters service is created, and that service reads
 * the shared Config\Filters: appending the chat filter to its globals['after']
 * here puts it on every page without editing the app's own config. Appended
 * (never replacing the app's list), and only once: in worker mode the config
 * outlives the request.
 */

use CodeIgniter\Events\Events;
use KolossusAI\ChatCodeIgniter\Filters\InjectChatWidget;

Events::on('pre_system', static function (): void {
    // Runs on every request of the site: never let it break one.
    try {
        $filters = config('Filters');
        if (is_object($filters)) {
            InjectChatWidget::addToGlobals($filters);
        }
    } catch (Throwable $e) {
        log_message('error', 'KolossusAI Chat: could not turn the chat on for every page: {message}', [
            'message' => $e->getMessage(),
        ]);
    }
});
