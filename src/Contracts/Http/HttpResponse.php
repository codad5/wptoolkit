<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Http;

/**
 * A received HTTP response (immutable).
 */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers Header names lowercase.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers = [],
        public readonly string $body = ''
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * The body decoded as JSON, or null when it isn't valid JSON.
     */
    public function json(): mixed
    {
        if ($this->body === '') {
            return null;
        }

        $decoded = json_decode($this->body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
