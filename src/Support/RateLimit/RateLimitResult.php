<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\RateLimit;

/**
 * The outcome of one rate-limited attempt.
 */
final class RateLimitResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $retryAfter
    ) {
    }

    /**
     * Standard response headers for this result.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [
            'X-RateLimit-Limit' => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) $this->remaining,
        ];
        if (!$this->allowed) {
            $headers['Retry-After'] = (string) $this->retryAfter;
        }

        return $headers;
    }
}
