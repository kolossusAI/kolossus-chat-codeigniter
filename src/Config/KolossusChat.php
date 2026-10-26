<?php

namespace KolossusAI\ChatCodeIgniter\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * KolossusAI Chat settings, read from .env (CodeIgniter loads it by itself):
 *
 *     KOLOSSUS_CHAT_WIDGET_TOKEN=wt_YOUR_TOKEN   # not set: the free FAQ assistant
 *     KOLOSSUS_CHAT_HOST_URL=https://...         # not set: the Kolossus cloud
 *
 * Everything else is optional. Empty look settings (theme, colour, layout, position,
 * offsets, size) mean "use the widget's own setting" from Kolossus. Invalid values are
 * ignored and logged as warnings.
 *
 * To change them in code instead, add app/Config/KolossusChat.php:
 *
 *     class KolossusChat extends \KolossusAI\ChatCodeIgniter\Config\KolossusChat
 *     {
 *         public array $excludePaths = ['admin', 'admin/*'];
 *     }
 */
class KolossusChat extends BaseConfig
{
    /** Your Kolossus server: https only (plain http:// only for localhost while testing). */
    public string $hostUrl = '';

    /** The PUBLIC widget token (wt_...), from your widget in the Kolossus dashboard. */
    public string $widgetToken = '';

    /**
     * Optional widget secret (ws_...). It stays on your server: it only signs the email
     * of a logged-in user (CodeIgniter Shield) so the chat knows who they are.
     */
    public string $widgetSecret = '';

    /** Add the chat bubble to every HTML page. */
    public bool $sitewide = true;

    /** @var list<string> Pages that never get the bubble: URL paths, "*" matches anything ("admin/*"). */
    public array $excludePaths = [];

    /** "", "light" or "dark". */
    public string $theme = '';

    /** "" or a hex colour like "#aaf684". */
    public string $primaryColor = '';

    /** "", "bubble", "sidebar" or "bottom_bar". */
    public string $layout = '';

    /** "remember" (as the visitor left it), "open" or "closed". */
    public string $openOnLoad = 'remember';

    /** Bubble corner: "", "right" or "left". */
    public string $position = '';

    /** Bubble distance from the side and bottom edges: "" or 0-1000 (pixels). */
    public string $offsetX = '';

    public string $offsetY = '';

    /** "", "small", "medium" or "large". */
    public string $bubbleSize = '';

    public function __construct()
    {
        $this->hostUrl = self::envText('KOLOSSUS_CHAT_HOST_URL', $this->hostUrl);
        $this->widgetToken = self::envText('KOLOSSUS_CHAT_WIDGET_TOKEN', $this->widgetToken);
        $this->widgetSecret = self::envText('KOLOSSUS_CHAT_WIDGET_SECRET', $this->widgetSecret);
        $this->sitewide = self::envFlag('KOLOSSUS_CHAT_SITEWIDE', $this->sitewide);
        $this->theme = self::envText('KOLOSSUS_CHAT_THEME', $this->theme);
        $this->primaryColor = self::envText('KOLOSSUS_CHAT_PRIMARY_COLOR', $this->primaryColor);
        $this->layout = self::envText('KOLOSSUS_CHAT_LAYOUT', $this->layout);
        $this->openOnLoad = self::envText('KOLOSSUS_CHAT_OPEN_ON_LOAD', $this->openOnLoad);
        $this->position = self::envText('KOLOSSUS_CHAT_POSITION', $this->position);
        $this->offsetX = self::envText('KOLOSSUS_CHAT_OFFSET_X', $this->offsetX);
        $this->offsetY = self::envText('KOLOSSUS_CHAT_OFFSET_Y', $this->offsetY);
        $this->bubbleSize = self::envText('KOLOSSUS_CHAT_BUBBLE_SIZE', $this->bubbleSize);

        // BaseConfig then applies kolossusChat.* .env overrides and registrars, as for
        // any CodeIgniter config.
        parent::__construct();
    }

    /** An .env value as text, or $default when it is not set or empty. */
    private static function envText(string $key, string $default): string
    {
        $value = env($key);
        if ($value === null || $value === false || $value === '' || ! is_scalar($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    /** An on/off .env value: "false" / "0" / "no" / "off" turn it off; not set keeps $default. */
    private static function envFlag(string $key, bool $default): bool
    {
        $value = env($key);
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['false', '0', 'no', 'off', '(false)'], true);
        }

        return (bool) $value;
    }
}
