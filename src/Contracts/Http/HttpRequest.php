<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Http;

/**
 * An outgoing HTTP request (immutable). Arrays given as a body are sent as JSON unless a
 * Content-Type says otherwise.
 */
final class HttpRequest
{
    /**
     * @param array<string, string> $headers Header names are stored lowercase.
     * @param array<string, mixed>|string|null $body
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly array|string|null $body = null,
        public readonly float $timeout = 10.0
    ) {
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers
     */
    public static function get(string $url, array $query = [], array $headers = []): self
    {
        return new self('GET', self::withQuery($url, $query), self::normalize($headers));
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, string> $headers
     */
    public static function post(string $url, array|string|null $body = null, array $headers = []): self
    {
        return new self('POST', $url, self::normalize($headers), $body);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->method, $this->url, [strtolower($name) => $value] + $this->headers, $this->body, $this->timeout);
    }

    public function withTimeout(float $seconds): self
    {
        return new self($this->method, $this->url, $this->headers, $this->body, $seconds);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * The body as it goes on the wire: JSON for arrays (unless a non-JSON Content-Type is set).
     */
    public function encodedBody(): ?string
    {
        if ($this->body === null || is_string($this->body)) {
            return $this->body;
        }

        $type = $this->header('content-type');
        if ($type !== null && !str_contains($type, 'json')) {
            return http_build_query($this->body);
        }

        return (string) json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Headers as sent, adding a JSON Content-Type when the body will be JSON.
     *
     * @return array<string, string>
     */
    public function sentHeaders(): array
    {
        if (is_array($this->body) && $this->header('content-type') === null) {
            return $this->headers + ['content-type' => 'application/json'];
        }

        return $this->headers;
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public static function withQuery(string $url, array $query): string
    {
        $query = array_filter($query, static fn ($value): bool => $value !== null);
        if ($query === []) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private static function normalize(array $headers): array
    {
        return array_change_key_case($headers, CASE_LOWER);
    }
}
