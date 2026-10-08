<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Cache;

use Codad5\WPToolkit\Adapters\Clock\SystemClock;
use Codad5\WPToolkit\Contracts\Clock\Clock;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Cache in the WordPress object cache (wp_cache_*). Persistent only when a persistent object cache
 * (Redis, Memcached, …) is installed; increment() is atomic there.
 */
final class ObjectCacheStore extends EnvelopeStore
{
    private readonly string $cacheGroup;

    private readonly Clock $clock;

    public function __construct(Identity $identity, string $group = 'default', ?Clock $clock = null)
    {
        $this->cacheGroup = $identity->transientKey('c:' . $group);
        $this->clock = $clock ?? new SystemClock();
    }

    protected function clock(): Clock
    {
        return $this->clock;
    }

    public function isPersistent(): bool
    {
        return function_exists('wp_using_ext_object_cache') && (bool) wp_using_ext_object_cache();
    }

    public function isAtomic(): bool
    {
        return $this->isPersistent();
    }

    /**
     * Native, atomic counters when a persistent object cache is installed. Stored as raw integers
     * (wp_cache_incr needs one) under their own keys; the TTL is set by the first wp_cache_add().
     */
    public function increment(string $key, int $by = 1, ?int $ttl = null): int
    {
        if (!$this->isPersistent()) {
            return parent::increment($key, $by, $ttl);
        }

        $counter = $this->backendKey('#raw-counter:' . $key, $this->generation());
        wp_cache_add($counter, 0, $this->cacheGroup, $ttl === null ? 0 : max(1, $ttl));
        $value = wp_cache_incr($counter, $by, $this->cacheGroup);

        return is_int($value) ? $value : $by;
    }

    public function count(string $key): int
    {
        if (!$this->isPersistent()) {
            return parent::count($key);
        }

        $value = wp_cache_get($this->backendKey('#raw-counter:' . $key, $this->generation()), $this->cacheGroup);

        return is_int($value) ? $value : 0;
    }

    protected function rawGet(string $key): mixed
    {
        return wp_cache_get($key, $this->cacheGroup);
    }

    protected function rawSet(string $key, mixed $value, int $ttl): bool
    {
        return wp_cache_set($key, $value, $this->cacheGroup, $ttl);
    }

    protected function rawDelete(string $key): bool
    {
        return wp_cache_delete($key, $this->cacheGroup);
    }

    protected function backendKey(string $key, int $generation): string
    {
        return $generation . ':' . $key;
    }

    protected function generationKey(): string
    {
        return 'generation';
    }
}
