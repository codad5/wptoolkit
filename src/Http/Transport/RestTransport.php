<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http\Transport;

use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Http\Response;
use Codad5\WPToolkit\Http\Route;
use Codad5\WPToolkit\Support\RateLimit\ClientIp;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Exposes routes on the WordPress REST API under the consumer's namespace (`my-plugin/v1`).
 *
 * WordPress's `permission_callback` is left open on purpose: access is decided by the Dispatcher,
 * which denies a route without a rule (ADR-0008) — one place for the rule on both transports.
 * WordPress core still verifies the X-WP-Nonce of cookie-authenticated requests before we run.
 */
final class RestTransport
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly Identity $identity,
        private readonly ClientIp $clientIp = new ClientIp()
    ) {
    }

    /**
     * @param list<Route> $routes
     */
    public function register(array $routes): void
    {
        foreach ($routes as $route) {
            register_rest_route($this->identity->restNamespace($route->apiVersion()), $route->restPattern(), [
                'methods' => $route->methods,
                'callback' => fn (WP_REST_Request $request): WP_REST_Response => $this->toWordPress(
                    $this->dispatcher->dispatch($route, $this->fromWordPress($request))
                ),
                'permission_callback' => '__return_true',
            ]);
        }
    }

    /**
     * Where the JS client sends this route: `{namespace}/{path}` under the REST root.
     *
     * @return array{namespace: string, path: string}
     */
    public function clientConfig(Route $route): array
    {
        return ['namespace' => $this->identity->restNamespace($route->apiVersion()), 'path' => trim($route->path, '/')];
    }

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     */
    public function fromWordPress(WP_REST_Request $request): Request
    {
        $headers = [];
        foreach ($request->get_headers() as $name => $values) {
            $headers[str_replace('_', '-', strtolower((string) $name))] = is_array($values) ? (string) reset($values) : (string) $values;
        }

        $body = $request->get_json_params() ?: $request->get_body_params();

        return new Request(
            method: $request->get_method(),
            transport: 'rest',
            routeParams: $request->get_url_params(),
            query: $request->get_query_params(),
            body: $body,
            headers: $headers,
            files: $request->get_file_params(),
            userId: get_current_user_id(),
            ip: $this->clientIp->resolve($_SERVER)
        );
    }

    public function toWordPress(Response $response): WP_REST_Response
    {
        $wp = new WP_REST_Response($response->data, $response->status);
        foreach ($response->headers as $name => $value) {
            $wp->header($name, $value);
        }

        return $wp;
    }
}
