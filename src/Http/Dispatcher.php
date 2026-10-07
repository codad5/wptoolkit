<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http;

use Closure;
use Codad5\WPToolkit\Contracts\Container\Container;
use Codad5\WPToolkit\Contracts\Http\Middleware;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Support\RateLimit\RateLimiter;
use Codad5\WPToolkit\Support\Validation\Validator;
use Throwable;

/**
 * The one request pipeline both transports use (ADR-0008), so every security rule is written once:
 *
 *   access rule → nonce (Ajax mutations) → rate limit → validation & sanitization → route
 *   middleware → controller → response
 *
 * Errors: an HttpError becomes its own status and safe message; anything else is logged with a
 * request ID and becomes a generic 500. Details reach the client only for administrators with
 * WP_DEBUG on.
 */
final class Dispatcher
{
    public function __construct(
        private readonly Container $container,
        private readonly Identity $identity,
        private readonly Logger $logger,
        private readonly ?RateLimiter $rateLimiter = null,
        private readonly bool $developmentMode = false
    ) {
    }

    public function dispatch(Route $route, Request $request): Response
    {
        $requestId = bin2hex(random_bytes(6));

        try {
            $this->authorize($route, $request);
            $this->verifyNonce($route, $request);
            $headers = $this->rateLimit($route, $request);
            $request = $this->validate($route, $request);

            $response = $this->throughMiddleware($route, $request, fn (Request $r): Response => $this->callController($route, $r));

            return $headers === [] ? $response : $response->withHeaders($headers);
        } catch (HttpError $error) {
            return Response::fromError($error);
        } catch (Throwable $error) {
            $this->logger->error('Request {request_id} to {route} failed: {exception}', [
                'request_id' => $requestId,
                'route' => $route->path,
                'exception' => $error,
            ]);

            $details = ['request_id' => $requestId];
            if ($this->developmentMode && $this->isAdministrator()) {
                $details['exception'] = $error::class . ': ' . $error->getMessage();
            }

            $internal = HttpError::internal();

            return Response::error($internal->status, $internal->errorCode, $internal->getMessage(), $details);
        }
    }

    /**
     * The access rule. A route without one is refused — at registration in development (Router),
     * and here in production, so a forgotten rule fails closed.
     */
    private function authorize(Route $route, Request $request): void
    {
        switch ($route->access()) {
            case 'public':
                return;
            case 'logged_in':
                if (!$request->isLoggedIn()) {
                    throw HttpError::unauthenticated();
                }
                return;
            case 'capability':
                if (!$request->isLoggedIn()) {
                    throw HttpError::unauthenticated();
                }
                if (!current_user_can((string) $route->capability())) {
                    throw HttpError::forbidden();
                }
                return;
            case 'policy':
                $policy = $route->policy();
                if ($policy === null || !$policy($request)) {
                    throw $request->isLoggedIn() ? HttpError::forbidden() : HttpError::unauthenticated();
                }
                return;
            default:
                $this->logger->warning('Route {route} has no access rule and was refused. Add ->public(), ->loggedIn(), ->can() or ->authorize().', [
                    'route' => $route->path,
                ]);
                throw HttpError::forbidden();
        }
    }

    /**
     * admin-ajax has no built-in CSRF protection for state changes; REST verifies its own
     * X-WP-Nonce for cookie-authenticated users in core. Public routes have no session to forge.
     */
    private function verifyNonce(Route $route, Request $request): void
    {
        if ($request->transport !== 'ajax' || !$route->isMutation() || $route->access() === 'public') {
            return;
        }

        $nonce = $request->header('x-wp-nonce') ?? (is_string($request->body['_wpnonce'] ?? null) ? $request->body['_wpnonce'] : '');
        if (wp_verify_nonce($nonce, $this->identity->nonceAction($route->routeName())) === false) {
            throw HttpError::forbidden(__('Your session has expired. Reload the page and try again.', 'wptoolkit'));
        }
    }

    /**
     * @return array<string, string> Rate-limit headers for the response.
     */
    private function rateLimit(Route $route, Request $request): array
    {
        $config = $route->rateLimitConfig();
        if ($config === null || $this->rateLimiter === null) {
            return [];
        }

        $bucket = $config['by'] === 'user' && $request->isLoggedIn() ? 'user:' . $request->userId : 'ip:' . $request->ip;
        $result = $this->rateLimiter->attempt($route->routeName() . ':' . $bucket, $config['max'], $config['seconds']);

        if (!$result->allowed) {
            throw HttpError::tooManyRequests($result->retryAfter, $result->headers());
        }

        return $result->headers();
    }

    private function validate(Route $route, Request $request): Request
    {
        $args = $route->declaredArgs();
        if ($args === []) {
            return $request->withValidated([]);
        }

        [$values, $errors] = (new Validator($args))->validate($request->raw());
        if ($errors !== []) {
            throw HttpError::validation($errors);
        }

        return $request->withValidated($values);
    }

    /**
     * @param Closure(Request): Response $controller
     */
    private function throughMiddleware(Route $route, Request $request, Closure $controller): Response
    {
        $next = $controller;
        foreach (array_reverse($route->middlewareStack()) as $middleware) {
            $instance = $middleware instanceof Middleware ? $middleware : $this->container->get($middleware);
            $next = static fn (Request $r): Response => $instance->handle($r, $next);
        }

        return $next($request);
    }

    private function callController(Route $route, Request $request): Response
    {
        $handler = $route->handler();
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [$this->container->get($handler[0]), $handler[1]];
        }
        if (!is_callable($handler)) {
            throw new \LogicException(sprintf('The handler for route %s is not callable.', $route->path));
        }

        $this->container->instance(Request::class, $request);

        return Response::from($this->container->call($handler, ['request' => $request]));
    }

    private function isAdministrator(): bool
    {
        return function_exists('current_user_can') && current_user_can('manage_options');
    }
}
