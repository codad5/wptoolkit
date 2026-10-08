<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Cache;

use Codad5\WPToolkit\Contracts\Cache\CacheStore;

/**
 * A cache that stores nothing: turns caching off without `if ($cache)` checks (Null Object).
 */
final class NullStore implements CacheStore
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return false;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function delete(string $key): bool
    {
        return false;
    }

    public function remember(string $key, ?int $ttl, callable $compute): mixed
    {
        return $compute();
    }

    public function increment(string $key, int $by = 1, ?int $ttl = null): int
    {
        return $by;
    }

    public function count(string $key): int
    {
        return 0;
    }

    public function clear(): bool
    {
        return true;
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function isAtomic(): bool
    {
        return false;
    }
}
