<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Frontend;

use Closure;
use Codad5\WPToolkit\Contracts\View\Renderer;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Http\Response;
use Codad5\WPToolkit\Http\Route;
use Codad5\WPToolkit\Support\RateLimit\ClientIp;

/**
 * Pretty front-end URLs rendered from views, without a placeholder page in the database (ports 0.x
 * `Page::addFrontendPage()`). A page is a Route, so it runs the same pipeline as REST and Ajax —
 * access rule (required), validation, rate limit, middleware — and its view can be overridden by
 * the theme under `{theme}/{slug}/…`.
 *
 *     $public->page('library/books/{id}', __('Book', 'my-plugin'), 'front/book', function (Request $r) use ($books): array {
 *         return ['book' => $books->find((int) $r->input('id')) ?? throw HttpError::notFound()];
 *     })->public()->args(['id' => ['rules' => 'required|integer']]);
 *
 * Responses: success renders inside the theme (classic header/footer, or block template parts);
 * 401 sends visitors to the login screen and back; 404 shows the theme's 404; other errors use wp_die().
 */
final class PublicPages
{
    /** @var array<string, array{route: Route, title: string|(Closure(Request): string), regex: string, params: list<string>}> */
    private array $pages = [];

    private ?string $title = null;

    private ?string $current = null;

    private bool $registered = false;

    /** @var Closure(): void */
    private readonly Closure $exit;

    /**
     * @param (Closure(): void)|null $exit Ends the request after a page is printed; replaceable in tests.
     */
    public function __construct(
        private readonly Identity $identity,
        private readonly HookRegistrar $hooks,
        private readonly Dispatcher $dispatcher,
        private readonly Renderer $renderer,
        private readonly ClientIp $clientIp = new ClientIp(),
        ?Closure $exit = null
    ) {
        $this->exit = $exit ?? static function (): void {
            exit;
        };
    }

    /**
     * Add a page at `$path` (`{name}` segments become route parameters). Returns its Route: give it
     * an access rule (`->public()`, `->loggedIn()`, `->can()`, `->authorize()`) and, for parameters, `->args()`.
     *
     * @param string|(Closure(Request): string) $title
     * @param (Closure(Request): array<string, mixed>)|null $data View data; throw HttpError::notFound() for a 404.
     */
    public function page(string $path, string|Closure $title, string $view, ?Closure $data = null): Route
    {
        $path = trim($path, '/');
        if (preg_match('#^[a-z0-9\-_/{}]+$#', $path) !== 1) {
            throw new InvalidConfigException(sprintf('Public page path "%s" may contain lowercase letters, digits, "-", "_", "/" and {params}.', $path));
        }
        $key = sanitize_key(str_replace('/', '-', preg_replace('/[{}]/', '', $path) ?? $path));
        if (isset($this->pages[$key])) {
            throw new InvalidConfigException(sprintf('Public page "%s" is already added.', $path));
        }

        preg_match_all('/\{([a-z_][a-z0-9_]*)\}/', $path, $matches);
        $regex = '^' . preg_replace('/\\\\\{[a-z_][a-z0-9_]*\\\\\}/', '([^/]+)', preg_quote($path, '#')) . '/?$';

        $route = (new Route(['GET'], $path, function (Request $request) use ($view, $data): string {
            return $this->renderer->render($view, $data === null ? [] : $data($request));
        }))->name('page_' . $key);

        $this->pages[$key] = ['route' => $route, 'title' => $title, 'regex' => $regex, 'params' => $matches[1]];

        return $route;
    }

    /**
     * The URL of a page, with its parameters filled in: `url('library/books/{id}', ['id' => 42])`.
     *
     * @param array<string, string|int> $params
     */
    public function url(string $path, array $params = []): string
    {
        $url = (string) preg_replace_callback('/\{([a-z_][a-z0-9_]*)\}/', static function (array $m) use ($params): string {
            return rawurlencode((string) ($params[$m[1]] ?? ''));
        }, trim($path, '/'));

        return home_url(user_trailingslashit($url));
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $this->hooks->addAction('init', [$this, 'addRewriteRules'], 20);
        $this->hooks->addFilter('query_vars', [$this, 'queryVars']);
        $this->hooks->addAction('template_redirect', [$this, 'serve'], 1);
        $this->hooks->addFilter('redirect_canonical', [$this, 'keepOurUrls']);
        $this->hooks->addFilter('pre_get_document_title', [$this, 'documentTitle']);
        $this->hooks->addFilter('body_class', [$this, 'bodyClass']);
    }

    /**
     * @internal Hooked to init. Rules are flushed only when they change (a stored hash), not per request.
     */
    public function addRewriteRules(): void
    {
        foreach ($this->pages as $key => $page) {
            $query = 'index.php?' . $this->identity->queryVar('page') . '=' . $key;
            foreach ($page['params'] as $i => $param) {
                $query .= '&' . $this->identity->queryVar('p_' . $param) . '=$matches[' . ($i + 1) . ']';
            }
            add_rewrite_rule($page['regex'], $query, 'top');
        }

        $hashOption = $this->identity->optionKey('rewrite_hash');
        $hash = md5((string) wp_json_encode(array_column($this->pages, 'regex')));
        if (get_option($hashOption) !== $hash) {
            flush_rewrite_rules(false);
            update_option($hashOption, $hash, true);
        }
    }

    /**
     * @internal
     * @param list<string> $vars
     * @return list<string>
     */
    public function queryVars(array $vars): array
    {
        $vars[] = $this->identity->queryVar('page');
        foreach ($this->pages as $page) {
            foreach ($page['params'] as $param) {
                $vars[] = $this->identity->queryVar('p_' . $param);
            }
        }

        return array_values(array_unique($vars));
    }

    /**
     * @internal Hooked to template_redirect.
     */
    public function serve(): void
    {
        $key = get_query_var($this->identity->queryVar('page'));
        if (!is_string($key) || !isset($this->pages[$key])) {
            return;
        }
        $page = $this->pages[$key];
        $this->current = $key;

        $params = [];
        foreach ($page['params'] as $param) {
            $params[$param] = (string) get_query_var($this->identity->queryVar('p_' . $param));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page; the Dispatcher validates declared args.
        $query = wp_unslash($_GET);
        $request = new Request(
            method: 'GET',
            transport: 'page',
            routeParams: $params,
            query: $query,
            userId: get_current_user_id(),
            ip: $this->clientIp->resolve($_SERVER)
        );

        $response = $this->dispatcher->dispatch($page['route'], $request);
        $this->respond($response, $page, $request);
    }

    /**
     * @param array{route: Route, title: string|(Closure(Request): string), regex: string, params: list<string>} $page
     */
    private function respond(Response $response, array $page, Request $request): void
    {
        if ($response->status === 401) {
            auth_redirect(); // to wp-login.php and back
            ($this->exit)();
            return;
        }

        if ($response->status === 404) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            return; // WordPress goes on to the theme's 404 template
        }

        if ($response->isError()) {
            $message = is_array($response->data) && is_string($response->data['message'] ?? null) ? $response->data['message'] : '';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the third argument is the HTTP status, not output.
            wp_die(esc_html($message), '', ['response' => $response->status]);
        }

        $this->title = is_string($page['title']) ? $page['title'] : ($page['title'])($request);
        status_header($response->status);
        foreach ($response->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        $this->printInTheme(is_string($response->data) ? $response->data : '');
        ($this->exit)();
    }

    private function printInTheme(string $html): void
    {
        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            echo '<!doctype html><html ';
            language_attributes();
            echo '><head><meta charset="' . esc_attr(get_bloginfo('charset')) . '" />';
            wp_head();
            echo '</head><body class="' . esc_attr(implode(' ', get_body_class())) . '">';
            wp_body_open();
            block_template_part('header');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered view, escaped in the template.
            echo '<main class="wp-block-group">' . $html . '</main>';
            block_template_part('footer');
            wp_footer();
            echo '</body></html>';
            return;
        }

        get_header();
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered view, escaped in the template.
        echo $html;
        get_footer();
    }

    /**
     * @internal Stops WordPress "correcting" our URLs to the home page.
     */
    public function keepOurUrls(mixed $redirect): mixed
    {
        $key = get_query_var($this->identity->queryVar('page'));

        return is_string($key) && isset($this->pages[$key]) ? false : $redirect;
    }

    /**
     * @internal
     */
    public function documentTitle(string $title): string
    {
        return $this->title !== null ? $this->title . ' ' . apply_filters('document_title_separator', '–') . ' ' . get_bloginfo('name') : $title;
    }

    /**
     * @internal
     * @param list<string> $classes
     * @return list<string>
     */
    public function bodyClass(array $classes): array
    {
        if ($this->current !== null) {
            $classes[] = $this->identity->handle('page');
            $classes[] = $this->identity->handle('page-' . $this->current);
        }

        return $classes;
    }

    /**
     * @return array<string, Route>
     */
    public function routes(): array
    {
        return array_map(static fn (array $p): Route => $p['route'], $this->pages);
    }
}
