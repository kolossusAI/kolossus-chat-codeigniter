<?php

/**
 * Place the chat by hand: load with helper('kolossus_chat') (or list 'kolossus_chat'
 * in app/Config/Autoload::$helpers), then in a view:
 *
 *     <?= kolossus_chat() ?>                              the chat bubble
 *     <?= kolossus_chat_button('Chat with us', 'btn') ?>  a button that opens the chat
 *     <?= kolossus_chat_script() ?>                       only the widget script
 *
 * A page that uses one of these is left alone by the site-wide filter. Each returns
 * '' when the chat is not set up, and never throws.
 */

if (! function_exists('kolossus_chat')) {
    function kolossus_chat(): string
    {
        return service('kolossusChat')->widget();
    }
}

if (! function_exists('kolossus_chat_button')) {
    /**
     * @param mixed $label
     * @param mixed $class
     */
    function kolossus_chat_button($label = null, $class = ''): string
    {
        return service('kolossusChat')->button($label, $class);
    }
}

if (! function_exists('kolossus_chat_script')) {
    function kolossus_chat_script(): string
    {
        return service('kolossusChat')->script();
    }
}
