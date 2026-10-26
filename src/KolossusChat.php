<?php

namespace KolossusAI\ChatCodeIgniter;

use CodeIgniter\HTTP\RequestInterface;
use KolossusAI\ChatCodeIgniter\Config\KolossusChat as KolossusChatConfig;
use Throwable;
use WeakMap;

/**
 * Builds the HTML that embeds the Kolossus chat widget (service('kolossusChat')).
 *
 * The widget script (served by the Kolossus server) opens the chat for the first
 * element with a data-kolossus attribute and binds every such element as a button.
 * The site-wide bubble is therefore a hidden marker element.
 */
class KolossusChat
{
    /** @var array<string, true> Problems already logged by this process. */
    private static array $logged = [];

    /**
     * Requests whose page already has the script tag: it goes out at most once per
     * page, and InjectChatWidget leaves a page that used a helper alone. Weak, so a
     * long-running worker forgets each request with it.
     *
     * @var WeakMap<RequestInterface, true>
     */
    private WeakMap $scriptDone;

    public function __construct()
    {
        $this->scriptDone = new WeakMap();
    }

    /** The validated settings; each distinct problem is logged once per process. */
    public function config(): ChatConfig
    {
        $cfg = ChatConfig::fromConfig(self::settings());
        foreach ($cfg->problems as $problem) {
            if (! isset(self::$logged[$problem])) {
                self::$logged[$problem] = true;
                log_message('warning', "KolossusAI Chat: {$problem} The value is ignored.");
            }
        }

        return $cfg;
    }

    /**
     * The settings: the app's own app/Config/KolossusChat.php when it has one, else the
     * package's. By the short name: CodeIgniter prefers the App version only for that,
     * never for a full class name. An app class that does not extend the package's is
     * logged and the package's used instead.
     */
    public static function settings(): KolossusChatConfig
    {
        $settings = config('KolossusChat');
        if ($settings instanceof KolossusChatConfig) {
            return $settings;
        }
        $problem = 'app/Config/KolossusChat.php must extend '.KolossusChatConfig::class.'.';
        if (! isset(self::$logged[$problem])) {
            self::$logged[$problem] = true;
            log_message('warning', "KolossusAI Chat: {$problem} The package's settings are used instead.");
        }

        return config(KolossusChatConfig::class);
    }

    /** kolossus_chat(): the site-wide chat bubble (the widget script plus a hidden marker). */
    public function widget(?RequestInterface $request = null): string
    {
        return $this->safely(function (ChatConfig $cfg) use ($request): string {
            $request ??= $this->currentRequest();

            return $this->scriptOnce($request, $cfg).'<div '.$this->triggerAttributes($cfg).' hidden aria-hidden="true"></div>';
        });
    }

    /**
     * kolossus_chat_button('Chat with us', 'btn'): a button that opens the chat. The
     * label is shown as escaped text; class names keep letters, digits, - and _ only.
     */
    public function button(mixed $label = null, mixed $class = ''): string
    {
        return $this->safely(function (ChatConfig $cfg) use ($label, $class): string {
            $extra = array_filter(array_map(
                static fn ($c) => preg_replace('/[^A-Za-z0-9_-]/', '', $c),
                preg_split('/\s+/', is_scalar($class) ? (string) $class : '') ?: []
            ));
            $label ??= 'Chat with us';
            $text = is_scalar($label) || $label instanceof \Stringable ? (string) $label : '';

            return $this->scriptOnce($this->currentRequest(), $cfg)
                .'<button type="button" class="'.esc(implode(' ', ['kolossus-chat-button', ...$extra])).'" '
                .$this->triggerAttributes($cfg).'>'.esc($text).'</button>';
        });
    }

    /** kolossus_chat_script(): only the widget script; add the triggers yourself. */
    public function script(): string
    {
        return $this->safely(fn (ChatConfig $cfg): string => $this->scriptOnce($this->currentRequest(), $cfg));
    }

    /** True when this request's page already has the widget script (from a helper). */
    public function scriptDone(?RequestInterface $request): bool
    {
        return $request !== null && isset($this->scriptDone[$request]);
    }

    /**
     * $build's HTML when the chat is configured, else nothing. An error is logged and
     * gives nothing too: the chat must never break the page it is on.
     *
     * @param callable(ChatConfig): string $build
     */
    private function safely(callable $build): string
    {
        try {
            $cfg = $this->config();

            return $cfg->configured() ? $build($cfg) : '';
        } catch (Throwable $e) {
            log_message('error', 'KolossusAI Chat: could not add the chat to the page: {message}', ['message' => $e->getMessage()]);

            return '';
        }
    }

    /** The widget script tag, at most once per request. */
    private function scriptOnce(?RequestInterface $request, ChatConfig $cfg): string
    {
        if ($request !== null) {
            if (isset($this->scriptDone[$request])) {
                return '';
            }
            $this->scriptDone[$request] = true;
        }
        $nonce = $this->cspNonce();

        // esc() in its html context, as Laravel's e(): safe in a double-quoted attribute
        // and keeps the URL readable (the attr context writes https&#x3A;&#x2F;...).
        return '<script src="'.esc($cfg->scriptUrl()).'" async'
            .($nonce !== null ? ' nonce="'.esc($nonce).'"' : '').'></script>';
    }

    /**
     * The page's Content-Security-Policy script nonce when the app turned CSP on
     * (Config\App::$CSPEnabled), else null. Without it a CSP would block the script.
     */
    private function cspNonce(): ?string
    {
        if (! config('App')->CSPEnabled) {
            return null;
        }
        $nonce = service('csp')->getScriptNonce();

        return $nonce !== '' ? $nonce : null;
    }

    /**
     * Escaped data-* attributes the widget script reads from a trigger element: the
     * token, optional look overrides, and the signed identity of a logged-in user.
     */
    private function triggerAttributes(ChatConfig $cfg): string
    {
        $attrs = [
            'data-kolossus' => $cfg->widgetToken,
            'data-kolossus-theme' => $cfg->theme,
            'data-kolossus-color' => $cfg->primaryColor,
            'data-kolossus-layout' => $cfg->layout,
            'data-kolossus-open' => $cfg->openOnLoad,
            'data-kolossus-position' => $cfg->position,
            'data-kolossus-offset-x' => $cfg->offsetX,
            'data-kolossus-offset-y' => $cfg->offsetY,
            'data-kolossus-size' => $cfg->bubbleSize,
        ] + $this->userIdentity($cfg->widgetSecret);

        $parts = [];
        foreach ($attrs as $name => $value) {
            if ($value !== '') {
                $parts[] = $name.'="'.esc($value).'"';
            }
        }

        return implode(' ', $parts);
    }

    /**
     * data-user-* attributes for a user logged in with CodeIgniter Shield, signed with
     * the widget secret. The secret stays on the server: only the HMAC of the email
     * reaches the browser. No secret, no Shield, or nobody logged in: none.
     *
     * @return array<string, string>
     */
    private function userIdentity(string $secret): array
    {
        if ($secret === '' || ! function_exists('auth')) {
            return [];
        }
        try {
            $auth = auth();
            if (! $auth->loggedIn()) {
                return [];
            }
            $user = $auth->user();
        } catch (Throwable) {
            // Shield not set up (or another auth() helper): nobody to sign.
            return [];
        }
        if ($user === null) {
            return [];
        }
        $email = strtolower($this->text($user->email ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [];
        }

        return [
            'data-user-email' => $email,
            'data-user-name' => $this->text($user->username ?? ''),
            'data-user-hash' => hash_hmac('sha256', $email, $secret),
        ];
    }

    /** A user field as trimmed text; '' for values that are not text (arrays, objects). */
    private function text(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : '';
    }

    private function currentRequest(): ?RequestInterface
    {
        try {
            return service('request');
        } catch (Throwable) {
            return null;
        }
    }
}
