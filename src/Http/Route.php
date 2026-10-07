<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http;

use Closure;
use Codad5\WPToolkit\Contracts\Http\Middleware;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Support\Validation\Rule;

/**
 * One route, built fluently (Builder). Declares who may call it — there is no default
 * (ADR-0008) — and how it's exposed.
 *
 *     $router->get('books/{id}', [BookController::class, 'show'])
 *         ->can('read')
 *         ->args(['id' => ['rules' => 'required|integer', 'type' => 'int']])
 *         ->rateLimit(60, perSeconds: 60)
 *         ->exposeVia('rest', 'ajax');
 */
final class Route
{
    /** @var 'public'|'logged_in'|'capability'|'policy'|null */
    private ?string $access = null;

    private ?string $capability = null;

    /** @var (Closure(Request): bool)|null */
    private ?Closure $policy = null;

    /** @var array<string, array{rules?: string|list<string|Rule>, type?: string, label?: string, default?: mixed}> */
    private array $args = [];

    /** @var array{max: int, seconds: int, by: string}|null */
    private ?array $rateLimit = null;

    /** @var list<'rest'|'ajax'> */
    private array $transports = ['rest'];

    /** @var list<Middleware|class-string<Middleware>> */
    private array $middleware = [];

    private string $version = 'v1';

    private ?string $name = null;

    /**
     * @param list<string> $methods
     * @param callable|array{class-string, string} $handler
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $path,
        private readonly mixed $handler
    ) {
        if (trim($path, '/') === '') {
            throw new InvalidConfigException('A route needs a path.');
        }
    }

    /** Anyone, including logged-out visitors. The one explicit way to make a route open. */
    public function public(): self
    {
        $this->access = 'public';

        return $this;
    }

    public function loggedIn(): self
    {
        $this->access = 'logged_in';

        return $this;
    }

    /** Logged in and holding a capability (or meta capability). */
    public function can(string $capability): self
    {
        $this->access = 'capability';
        $this->capability = $capability;

        return $this;
    }

    /**
     * Custom authorization, e.g. "may edit this specific post".
     *
     * @param Closure(Request): bool $policy
     */
    public function authorize(Closure $policy): self
    {
        $this->access = 'policy';
        $this->policy = $policy;

        return $this;
    }

    /**
     * Declared input: only these fields reach `Request::input()`, validated and sanitized.
     *
     * @param array<string, array{rules?: string|list<string|Rule>, type?: string, label?: string, default?: mixed}> $args
     */
    public function args(array $args): self
    {
        $this->args = $args;

        return $this;
    }

    /**
     * @param 'ip'|'user' $by Bucket per client IP or per logged-in user (falls back to IP).
     */
    public function rateLimit(int $max, int $perSeconds = 60, string $by = 'ip'): self
    {
        $this->rateLimit = ['max' => $max, 'seconds' => $perSeconds, 'by' => $by];

        return $this;
    }

    /**
     * Where the route is reachable: 'rest' (default), 'ajax', or both.
     */
    public function exposeVia(string ...$transports): self
    {
        $valid = [];
        foreach ($transports as $transport) {
            $valid[] = match ($transport) {
                'rest' => 'rest',
                'ajax' => 'ajax',
                default => throw new InvalidConfigException(sprintf('Unknown transport "%s"; use rest or ajax.', $transport)),
            };
        }
        $this->transports = array_values(array_unique($valid));

        return $this;
    }

    /**
     * @param Middleware|class-string<Middleware> ...$middleware
     */
    public function middleware(Middleware|string ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * The name used for the Ajax action and nonce; defaults to the path (`books/{id}` → `books_id`).
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    // --- Read side, used by the dispatcher and transports ------------------------------------

    public function access(): ?string
    {
        return $this->access;
    }

    public function capability(): ?string
    {
        return $this->capability;
    }

    /**
     * @return (Closure(Request): bool)|null
     */
    public function policy(): ?Closure
    {
        return $this->policy;
    }

    /**
     * @return array<string, array{rules?: string|list<string|Rule>, type?: string, label?: string, default?: mixed}>
     */
    public function declaredArgs(): array
    {
        return $this->args;
    }

    /**
     * @return array{max: int, seconds: int, by: string}|null
     */
    public function rateLimitConfig(): ?array
    {
        return $this->rateLimit;
    }

    /**
     * @return list<'rest'|'ajax'>
     */
    public function transports(): array
    {
        return $this->transports;
    }

    /**
     * @return list<Middleware|class-string<Middleware>>
     */
    public function middlewareStack(): array
    {
        return $this->middleware;
    }

    public function apiVersion(): string
    {
        return $this->version;
    }

    public function routeName(): string
    {
        return $this->name ?? trim((string) preg_replace('/[^a-z0-9]+/i', '_', $this->path), '_');
    }

    /**
     * @return callable|array{class-string, string}
     */
    public function handler(): mixed
    {
        return $this->handler;
    }

    public function isMutation(): bool
    {
        return array_diff($this->methods, ['GET', 'HEAD', 'OPTIONS']) !== [];
    }

    /**
     * The path as a WordPress REST regex: `books/{id}` → `/books/(?P<id>[^/]+)`.
     *
     * @return non-falsy-string
     */
    public function restPattern(): string
    {
        return '/' . (string) preg_replace('/\{([a-z_][a-z0-9_]*)\}/i', '(?P<$1>[^/]+)', trim($this->path, '/'));
    }
}
