<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Cache;

use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Contracts\Clock\Clock;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Config;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Builds the cache store named by config (`'cache' => ['driver' => …]`), so switching backends is
 * a config change, never a code change (ADR-0004).
 *
 * Drivers: `auto` (default — the object cache when a persistent one is installed, else
 * transients), `object`, `transient`, `array`, `null`.
 */
final class CacheFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly Identity $identity,
        private readonly Clock $clock
    ) {
    }

    public function make(string $group = 'default', ?string $driver = null): CacheStore
    {
        $driver ??= (string) $this->config->get('cache.driver', 'auto');

        return match ($driver) {
            'auto' => $this->hasPersistentObjectCache()
                ? new ObjectCacheStore($this->identity, $group, $this->clock)
                : new TransientStore($this->identity, $group, $this->clock),
            'object' => new ObjectCacheStore($this->identity, $group, $this->clock),
            'transient' => new TransientStore($this->identity, $group, $this->clock),
            'array' => new ArrayStore($this->clock),
            'null' => new NullStore(),
            default => throw new InvalidConfigException(sprintf(
                'Unknown cache driver "%s". Use auto, object, transient, array or null.',
                $driver
            )),
        };
    }

    private function hasPersistentObjectCache(): bool
    {
        return function_exists('wp_using_ext_object_cache') && (bool) wp_using_ext_object_cache();
    }
}
