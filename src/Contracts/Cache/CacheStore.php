<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Cache;

/**
 * A key/value cache for one consumer and one group (ADR-0004). Shaped like PSR-16 without
 * depending on it (ADR-0012).
 *
 * Every value round-trips exactly, including `false`, `null` and `0` — a WordPress transient alone
 * can't tell a cached `false` from a miss.
 */
interface CacheStore
{
    public function get(string $key, mixed $default = null): mixed;

    /**
     * @param int|null $ttl Seconds until expiry; null means no expiry.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    /**
     * Return the cached value, or compute, store and return it.
     *
     * @template T
     * @param callable(): T $compute
     * @return T
     */
    public function remember(string $key, ?int $ttl, callable $compute): mixed;

    /**
     * Add to an integer counter (created at 0) and return the new value. Atomic only where the
     * backend is; see isAtomic().
     *
     * Counters live in their own key space: read them with count(), not get(). The TTL applies
     * when the counter is created.
     */
    public function increment(string $key, int $by = 1, ?int $ttl = null): int;

    /**
     * The current value of a counter, 0 when it doesn't exist (or expired).
     */
    public function count(string $key): int;

    /**
     * Drop every entry in this store's group.
     */
    public function clear(): bool;

    /**
     * Whether values survive the current request. Rate limiting refuses stores that don't.
     */
    public function isPersistent(): bool;

    /**
     * Whether increment() is atomic across concurrent requests.
     */
    public function isAtomic(): bool;
}
