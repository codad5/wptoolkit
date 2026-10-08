<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Cache;

use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Exposes a WPToolkit CacheStore as PSR-16, for libraries that expect a simple cache.
 *
 * Only usable when the consumer installs `psr/simple-cache` (ADR-0012).
 */
final class Psr16Bridge implements CacheInterface
{
    public function __construct(private readonly CacheStore $store)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($this->check($key), $default);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $seconds = $this->seconds($ttl);
        if ($seconds !== null && $seconds <= 0) {
            // PSR-16: a non-positive TTL deletes the item.
            $this->delete($key);
            return true;
        }

        return $this->store->set($this->check($key), $value, $seconds);
    }

    public function delete(string $key): bool
    {
        $this->store->delete($this->check($key));

        return true;
    }

    public function clear(): bool
    {
        return $this->store->clear();
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $this->set((string) $key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->store->has($this->check($key));
    }

    private function seconds(null|int|DateInterval $ttl): ?int
    {
        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable();
            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        return $ttl;
    }

    /**
     * PSR-16 reserves these characters in keys.
     */
    private function check(string $key): string
    {
        if ($key === '' || strpbrk($key, '{}()/\\@:') !== false) {
            throw new class (sprintf('Invalid PSR-16 cache key "%s".', $key)) extends \InvalidArgumentException implements InvalidArgumentException {
            };
        }

        return $key;
    }
}
