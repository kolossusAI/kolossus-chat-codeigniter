<?php

namespace KolossusAI\ChatCodeIgniter\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Adds the chat bubble to every HTML page (an "after" filter), so no view has to change.
 *
 * Config/Events.php appends it to Filters::$globals['after']. The widget script and the
 * hidden bubble marker go in just before the last </body>. A page is left exactly as
 * it is when:
 *
 * - the settings are not usable, or sitewide is off;
 * - the path matches excludePaths;
 * - the response is not a complete HTML page (not 200, not text/html, a download,
 *   compressed, an attachment, or without </body>);
 * - it is an AJAX request or a partial page for htmx, Turbo or Inertia;
 * - the page already used a helper (kolossus_chat(), ...), or already holds a
 *   data-kolossus element or the widget script (the site placed the chat itself).
 *
 * Any error while adding the bubble is logged and the original page is returned.
 */
class InjectChatWidget implements FilterInterface
{
    public const ALIAS = 'kolossusChat';

    /** Request headers of partial page updates, which must not get a second bubble. */
    private const PARTIAL_HEADERS = ['HX-Request', 'Turbo-Frame', 'X-Inertia'];

    /**
     * Appends the filter to $filters->globals['after'] (Config\Filters), once. Never
     * replaces the app's own list; does nothing when the app lists it already (also
     * with 'except' rules, as 'kolossusChat' => ['except' => [...]]).
     */
    public static function addToGlobals(object $filters): void
    {
        if (! isset($filters->aliases) || ! is_array($filters->aliases)) {
            return;
        }
        $filters->aliases[self::ALIAS] ??= self::class;
        $globals = isset($filters->globals) && is_array($filters->globals) ? $filters->globals : [];
        $after = isset($globals['after']) && is_array($globals['after']) ? $globals['after'] : [];
        foreach ($after as $key => $value) {
            if ($key === self::ALIAS || $value === self::ALIAS) {
                return;
            }
        }
        $after[] = self::ALIAS;
        $globals['after'] = $after;
        $filters->globals = $globals;
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        try {
            $this->addWidget($request, $response);
        } catch (Throwable $e) {
            log_message('error', 'KolossusAI Chat: could not add the chat bubble; the page is sent without it: {message}', [
                'message' => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function addWidget(RequestInterface $request, ResponseInterface $response): void
    {
        if (
            $response instanceof DownloadResponse
            || $response->getStatusCode() !== 200
            || ! str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'text/html')
            || $response->hasHeader('Content-Encoding')
            || str_contains(strtolower($response->getHeaderLine('Content-Disposition')), 'attachment')
            || ($request instanceof IncomingRequest && $request->isAJAX())
        ) {
            return;
        }
        foreach (self::PARTIAL_HEADERS as $header) {
            if ($request->hasHeader($header)) {
                return;
            }
        }

        $chat = service('kolossusChat');
        if ($chat->scriptDone($request)) {
            return;
        }
        $cfg = $chat->config();
        if (! $cfg->configured() || ! $cfg->sitewide) {
            return;
        }
        // getPath(): relative to baseURL, so it also matches on a site in a subfolder.
        if ($cfg->excludePaths !== [] && $request instanceof IncomingRequest && $cfg->excludes($request->getPath())) {
            return;
        }

        $body = $response->getBody();
        // Our own script URL only: a site may well have its own /static/widget/widget.js.
        if (! is_string($body) || str_contains($body, 'data-kolossus=') || str_contains($body, $cfg->scriptUrl())) {
            return;
        }
        // The last </body> of the page; tolerant of case and whitespace (</BODY >).
        if (! preg_match_all('#</body\s*>#i', $body, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $at = end($matches[0])[1];

        $html = $chat->widget($request);
        if ($html === '') {
            return;
        }
        $response->setBody(substr($body, 0, $at).$html.substr($body, $at));
        if ($response->hasHeader('Content-Length')) {
            $response->setHeader('Content-Length', (string) strlen((string) $response->getBody()));
        }
    }
}
