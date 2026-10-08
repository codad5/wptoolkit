<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Cache;

use Codad5\WPToolkit\Adapters\Cache\ObjectCacheStore;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Contract\CacheStoreContract;
use Codad5\WPToolkit\Tests\Support\FakeWordPressCache;

/**
 * With a persistent object cache (Redis, Memcached): native atomic counters.
 */
final class ObjectCacheStoreTest extends CacheStoreContract
{
    protected function setUp(): void
    {
        parent::setUp();
        (new FakeWordPressCache($this->clock, persistentObjectCache: true))->install();
    }

    protected function makeStore(string $group = 'default'): CacheStore
    {
        return new ObjectCacheStore(new Identity('my-plugin'), $group, $this->clock);
    }

    public function test_is_persistent_and_atomic_with_an_external_object_cache(): void
    {
        self::assertTrue($this->makeStore()->isPersistent());
        self::assertTrue($this->makeStore()->isAtomic());
    }
}
