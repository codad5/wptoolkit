<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Cache;

use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Contracts\Clock\Clock;

/**
 * Shared behaviour for stores over a raw backend that returns `false` for a miss.
 *
 * Values are stored inside an envelope (`['v' => value]`), so a cached `false`, `null` or `0` is
 * never mistaken for a miss. Keys carry the group's generation number, so clear() is one counter
 * increment instead of a scan of the options table.
 */
abstract class EnvelopeStore implements CacheStore
{
    abstract protected function rawGet(string $key): mixed;

    abstract protected function rawSet(string $key, mixed $value, int $ttl): bool;

    abstract protected function rawDelete(string $key): bool;

    /**
     * The full backend key for a cache key, within the current generation.
     */
    abstract protected function backendKey(string $key, int $generation): string;

    /**
     * The backend key holding the generation counter itself.
     */
    abstract protected function generationKey(): string;

    public function get(string $key, mixed $default = null): mixed
    {
        $envelope = $this->rawGet($this->backendKey($key, $this->generation()));

        return is_array($envelope) && array_key_exists('v', $envelope) ? $envelope['v'] : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->rawSet($this->backendKey($key, $this->generation()), ['v' => $value], $this->normalizeTtl($ttl));
    }

    public function has(string $key): bool
    {
        $envelope = $this->rawGet($this->backendKey($key, $this->generation()));

        return is_array($envelope) && array_key_exists('v', $envelope);
    }

    public function delete(string $key): bool
    {
        return $this->rawDelete($this->backendKey($key, $this->generation()));
    }

    public function remember(string $key, ?int $ttl, callable $compute): mixed
    {
        $envelope = $this->rawGet($this->backendKey($key, $this->generation()));
        if (is_array($envelope) && array_key_exists('v', $envelope)) {
            return $envelope['v'];
        }

        $value = $compute();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function increment(string $key, int $by = 1, ?int $ttl = null): int
    {
        $counterKey = $this->backendKey('#counter:' . $key, $this->generation());
        $envelope = $this->rawGet($counterKey);

        if (is_array($envelope) && is_int($envelope['v'] ?? null)) {
            // Keep the original expiry: a counter's window starts when it is created.
            $expires = is_int($envelope['e'] ?? null) ? $envelope['e'] : 0;
            $remaining = $expires > 0 ? max(1, $expires - $this->now()) : 0;
            $next = $envelope['v'] + $by;
            $this->rawSet($counterKey, ['v' => $next, 'e' => $expires], $remaining);

            return $next;
        }

        $seconds = $ttl === null ? 0 : max(1, $ttl);
        $this->rawSet($counterKey, ['v' => $by, 'e' => $seconds > 0 ? $this->now() + $seconds : 0], $seconds);

        return $by;
    }

    public function count(string $key): int
    {
        $envelope = $this->rawGet($this->backendKey('#counter:' . $key, $this->generation()));

        return is_array($envelope) && is_int($envelope['v'] ?? null) ? $envelope['v'] : 0;
    }

    /**
     * Current Unix time from the store's clock.
     */
    protected function now(): int
    {
        return $this->clock()->now()->getTimestamp();
    }

    abstract protected function clock(): Clock;

    public function clear(): bool
    {
        return $this->rawSet($this->generationKey(), ['v' => $this->generation() + 1], 0);
    }

    public function isAtomic(): bool
    {
        return false;
    }

    protected function generation(): int
    {
        $envelope = $this->rawGet($this->generationKey());

        return is_array($envelope) && is_int($envelope['v'] ?? null) ? $envelope['v'] : 0;
    }

    /**
     * Backends take 0 for "no expiry"; a non-positive TTL means "already expired", stored for 1s.
     */
    private function normalizeTtl(?int $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        return max(1, $ttl);
    }
}
