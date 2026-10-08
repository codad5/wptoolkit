<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;

/**
 * In-memory stand-ins for WordPress's transient and object-cache functions, with real expiry
 * driven by a FrozenClock. Behaves like WordPress where it matters to the adapters: a miss is
 * `false`, TTL 0 means no expiry, wp_cache_add() doesn't overwrite, wp_cache_incr() fails on a
 * missing key.
 */
final class FakeWordPressCache
{
    /** @var array<string, array{value: mixed, expires: int}> */
    public array $transients = [];

    /** @var array<string, array{value: mixed, expires: int}> */
    public array $objects = [];

    public function __construct(private readonly FrozenClock $clock, private readonly bool $persistentObjectCache = false)
    {
    }

    public function install(): void
    {
        Functions\when('get_transient')->alias(fn (string $key) => $this->read($this->transients, $key));
        Functions\when('set_transient')->alias(fn (string $key, $value, int $ttl = 0) => $this->write($this->transients, $key, $value, $ttl));
        Functions\when('delete_transient')->alias(fn (string $key) => $this->remove($this->transients, $key));

        Functions\when('wp_cache_get')->alias(fn (string $key, string $group = '') => $this->read($this->objects, "{$group}|{$key}"));
        Functions\when('wp_cache_set')->alias(fn (string $key, $value, string $group = '', int $ttl = 0) => $this->write($this->objects, "{$group}|{$key}", $value, $ttl));
        Functions\when('wp_cache_delete')->alias(fn (string $key, string $group = '') => $this->remove($this->objects, "{$group}|{$key}"));
        Functions\when('wp_cache_add')->alias(function (string $key, $value, string $group = '', int $ttl = 0): bool {
            if ($this->read($this->objects, "{$group}|{$key}") !== false) {
                return false;
            }
            return $this->write($this->objects, "{$group}|{$key}", $value, $ttl);
        });
        Functions\when('wp_cache_incr')->alias(function (string $key, int $by = 1, string $group = '') {
            $current = $this->read($this->objects, "{$group}|{$key}");
            if (!is_int($current)) {
                return false;
            }
            $this->objects["{$group}|{$key}"]['value'] = $current + $by;
            return $current + $by;
        });
        Functions\when('wp_using_ext_object_cache')->justReturn($this->persistentObjectCache);
    }

    /**
     * @param array<string, array{value: mixed, expires: int}> $store
     */
    private function read(array &$store, string $key): mixed
    {
        if (!isset($store[$key])) {
            return false;
        }
        if ($store[$key]['expires'] !== 0 && $store[$key]['expires'] <= $this->clock->now()->getTimestamp()) {
            unset($store[$key]);
            return false;
        }

        return $store[$key]['value'];
    }

    /**
     * @param array<string, array{value: mixed, expires: int}> $store
     */
    private function write(array &$store, string $key, mixed $value, int $ttl): bool
    {
        $store[$key] = ['value' => $value, 'expires' => $ttl > 0 ? $this->clock->now()->getTimestamp() + $ttl : 0];

        return true;
    }

    /**
     * @param array<string, array{value: mixed, expires: int}> $store
     */
    private function remove(array &$store, string $key): bool
    {
        $existed = isset($store[$key]);
        unset($store[$key]);

        return $existed;
    }
}
