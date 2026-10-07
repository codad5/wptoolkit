<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http\Transport;

use Closure;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Http\Response;
use Codad5\WPToolkit\Http\Route;
use Codad5\WPToolkit\Support\RateLimit\ClientIp;

/**
 * Exposes routes through admin-ajax as `{slug}_{route}` actions. Logged-out access
 * (`wp_ajax_nopriv_…`) is registered only for `->public()` routes; the Dispatcher still checks.
 *
 * Responses send the real HTTP status (0.x always sent 200), with the same body shape as REST.
 */
final class AjaxTransport
{
    /** @var Closure(Response): void */
    private readonly Closure $send;

    /**
     * @param (Closure(Response): void)|null $send How to emit the response; defaults to wp_send_json().
     */
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly Identity $identity,
        private readonly HookRegistrar $hooks,
        private readonly ClientIp $clientIp = new ClientIp(),
        ?Closure $send = null
    ) {
        $this->send = $send ?? static function (Response $response): void {
            foreach ($response->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            wp_send_json($response->data, $response->status);
        };
    }

    /**
     * @param list<Route> $routes
     */
    public function register(array $routes): void
    {
        // Routes on one path (GET and POST `todos`) share an action: one handler picks by method.
        // Separate handlers would let the first answer 405 and exit before the right one ran.
        $byAction = [];
        foreach ($routes as $route) {
            $byAction[$this->identity->ajaxAction($route->routeName())][] = $route;
        }

        foreach ($byAction as $action => $group) {
            $handler = fn () => $this->handle(...$group);

            $this->hooks->addAction('wp_ajax_' . $action, $handler);
            foreach ($group as $route) {
                if ($route->access() === 'public') {
                    $this->hooks->addAction('wp_ajax_nopriv_' . $action, $handler);
                    break;
                }
            }
        }
    }

    /**
     * Dispatch to whichever of the action's routes accepts the request method; 405 if none does.
     */
    public function handle(Route $route, Route ...$siblings): void
    {
        $method = strtoupper(sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'GET')));
        foreach ([$route, ...$siblings] as $candidate) {
            if (in_array($method, $candidate->methods, true)) {
                ($this->send)($this->dispatcher->dispatch($candidate, $this->fromGlobals($method)));
                return;
            }
        }

        ($this->send)(Response::error(405, 'method_not_allowed', __('This request method is not allowed here.', 'wptoolkit')));
    }

    /**
     * The action's name and nonce, for the JS client (Phase 5 localizes these).
     *
     * @return array{action: string, nonce: string}
     */
    public function clientConfig(Route $route): array
    {
        return [
            'action' => $this->identity->ajaxAction($route->routeName()),
            'nonce' => wp_create_nonce($this->identity->nonceAction($route->routeName())),
        ];
    }

    private function fromGlobals(string $method): Request
    {
        // Nonce verification and sanitization happen in the Dispatcher for every route, by design.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
        $query = wp_unslash($_GET);
        $body = wp_unslash($_POST);
        $files = $_FILES;
        // phpcs:enable

        unset($query['action'], $body['action']);

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'HTTP_') && is_string($value)) {
                $headers[str_replace('_', '-', strtolower(substr($key, 5)))] = $value;
            }
        }

        return new Request(
            method: $method,
            transport: 'ajax',
            query: $query,
            body: $body,
            headers: $headers,
            files: $files,
            userId: get_current_user_id(),
            ip: $this->clientIp->resolve($_SERVER)
        );
    }
}
