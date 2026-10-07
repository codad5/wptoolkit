<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http;

use Codad5\WPToolkit\Exceptions\LifecycleException;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Http\Transport\AjaxTransport;
use Codad5\WPToolkit\Http\Transport\RestTransport;

/**
 * Collects routes and registers them with WordPress — REST and/or admin-ajax — through the same
 * Dispatcher (ADR-0008). Replaces 0.x `Ajax` and `RestRoute`.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    private bool $registered = false;

    public function __construct(
        private readonly HookRegistrar $hooks,
        private readonly RestTransport $rest,
        private readonly AjaxTransport $ajax,
        private readonly bool $developmentMode = false
    ) {
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function get(string $path, callable|array $handler): Route
    {
        return $this->add(['GET'], $path, $handler);
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function post(string $path, callable|array $handler): Route
    {
        return $this->add(['POST'], $path, $handler);
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function put(string $path, callable|array $handler): Route
    {
        return $this->add(['PUT'], $path, $handler);
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function patch(string $path, callable|array $handler): Route
    {
        return $this->add(['PATCH'], $path, $handler);
    }

    /**
     * @param callable|array{class-string, string} $handler
     */
    public function delete(string $path, callable|array $handler): Route
    {
        return $this->add(['DELETE'], $path, $handler);
    }

    /**
     * @param list<string> $methods
     * @param callable|array{class-string, string} $handler
     */
    public function add(array $methods, string $path, callable|array $handler): Route
    {
        if ($this->registered) {
            throw new LifecycleException('Routes must be added before rest_api_init / admin-ajax handling starts.');
        }

        $route = new Route(array_map('strtoupper', $methods), $path, $handler);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Hook the transports. Called once the providers have booted.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        foreach ($this->routes as $route) {
            if ($route->access() === null && $this->developmentMode) {
                throw new LifecycleException(sprintf(
                    'Route %s %s has no access rule. Add ->public(), ->loggedIn(), ->can() or ->authorize(). (In production it would be refused with 403.)',
                    implode('|', $route->methods),
                    $route->path
                ));
            }
        }

        // If the REST server already initialised (a late boot), register now instead of waiting
        // for an action that won't fire again.
        if (function_exists('did_action') && did_action('rest_api_init') > 0) {
            $this->rest->register($this->routesFor('rest'));
        } else {
            $this->hooks->addAction('rest_api_init', fn () => $this->rest->register($this->routesFor('rest')));
        }
        $this->ajax->register($this->routesFor('ajax'));
    }

    /**
     * Every route, for `routes:list` and tests.
     *
     * @return list<Route>
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * @param 'rest'|'ajax' $transport
     * @return list<Route>
     */
    private function routesFor(string $transport): array
    {
        return array_values(array_filter($this->routes, static fn (Route $r): bool => in_array($transport, $r->transports(), true)));
    }
}
