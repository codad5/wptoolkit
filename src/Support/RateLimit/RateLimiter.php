<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\RateLimit;

use Codad5\WPToolkit\Adapters\Log\NullLogger;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Contracts\Clock\Clock;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Fixed-window rate limiting over a CacheStore (fixes 0.x C3, whose limiter used the non-persistent
 * object cache and so never limited anything on most hosts).
 *
 * Windows are aligned to the clock — `floor(now / window)` — so Retry-After is exact. The counter
 * lives in the cache with the window as its TTL. Atomic when the store is (a persistent object
 * cache); with transients two simultaneous requests may both get through at the edge, which is the
 * usual, accepted imprecision.
 *
 * A store that doesn't persist across requests can't limit anything: that is logged as a warning,
 * once per limiter, instead of failing silently.
 */
final class RateLimiter
{
    private bool $warned = false;

    public function __construct(
        private readonly CacheStore $store,
        private readonly Clock $clock,
        private readonly Logger $logger = new NullLogger()
    ) {
    }

    /**
     * Count one attempt for `$key` and say whether it is within `$max` per `$windowSeconds`.
     */
    public function attempt(string $key, int $max, int $windowSeconds): RateLimitResult
    {
        if ($max < 1 || $windowSeconds < 1) {
            throw new InvalidConfigException('A rate limit needs at least 1 attempt per at least 1 second.');
        }

        $this->warnIfNotPersistent();

        $now = $this->clock->now()->getTimestamp();
        $window = intdiv($now, $windowSeconds);
        $retryAfter = ($window + 1) * $windowSeconds - $now;

        $count = $this->store->increment($this->counterKey($key, $windowSeconds, $window), 1, $windowSeconds);

        return new RateLimitResult(
            allowed: $count <= $max,
            limit: $max,
            remaining: max(0, $max - $count),
            retryAfter: $retryAfter
        );
    }

    /**
     * How many attempts `$key` has used in the current window, without counting one.
     */
    public function used(string $key, int $windowSeconds): int
    {
        $window = intdiv($this->clock->now()->getTimestamp(), $windowSeconds);

        return $this->store->count($this->counterKey($key, $windowSeconds, $window));
    }

    private function counterKey(string $key, int $windowSeconds, int $window): string
    {
        return "rate:{$windowSeconds}:{$window}:{$key}";
    }

    private function warnIfNotPersistent(): void
    {
        if ($this->warned || $this->store->isPersistent()) {
            return;
        }

        $this->warned = true;
        $this->logger->warning(
            'Rate limiting uses a cache that does not persist between requests, so limits only apply within one request. '
            . 'Use the transient or a persistent object cache driver.'
        );
    }
}
