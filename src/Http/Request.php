<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http;

/**
 * An incoming request, the same shape whether it arrived over REST or admin-ajax (immutable).
 *
 * Input is already unslashed. After validation, `input()` returns the sanitized values the route
 * declared; undeclared input stays reachable through `raw()` for the rare case that needs it.
 */
final class Request
{
    /**
     * @param 'rest'|'ajax' $transport
     * @param array<string, mixed> $routeParams Values captured from the route path.
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers Lowercase names.
     * @param array<string, mixed> $files
     * @param array<string, mixed> $validated Set by the dispatcher after validation.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $transport,
        public readonly array $routeParams = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $files = [],
        public readonly int $userId = 0,
        public readonly string $ip = '0.0.0.0',
        private readonly ?array $validated = null
    ) {
    }

    /**
     * A declared, validated and sanitized input value — route params, then body, then query.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $source = $this->validated ?? $this->raw();

        return array_key_exists($key, $source) ? $source[$key] : $default;
    }

    /**
     * Every declared, validated and sanitized input.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        return $this->validated ?? [];
    }

    /**
     * All input as received (unslashed, not sanitized): route params override body override query.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->routeParams + $this->body + $this->query;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isLoggedIn(): bool
    {
        return $this->userId > 0;
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function withValidated(array $validated): self
    {
        return new self(
            $this->method,
            $this->transport,
            $this->routeParams,
            $this->query,
            $this->body,
            $this->headers,
            $this->files,
            $this->userId,
            $this->ip,
            $validated
        );
    }
}
