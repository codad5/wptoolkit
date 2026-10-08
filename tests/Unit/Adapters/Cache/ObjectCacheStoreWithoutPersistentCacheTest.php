<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Cache;

use Codad5\WPToolkit\Adapters\Cache\ObjectCacheStore;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Contract\CacheStoreContract;
use Codad5\WPToolkit\Tests\Support\FakeWordPressCache;

/**
 * WordPress's default per-request object cache: the same contract, but not persistent.
 */
final class ObjectCacheStoreWithoutPersistentCacheTest extends CacheStoreContract
{
    protected function setUp(): void
    {
        parent::setUp();
        (new FakeWordPressCache($this->clock, persistentObjectCache: false))->install();
    }

    protected function makeStore(string $group = 'default'): CacheStore
    {
        return new ObjectCacheStore(new Identity('my-plugin'), $group, $this->clock);
    }

    public function test_reports_that_it_is_not_persistent(): void
    {
        self::assertFalse($this->makeStore()->isPersistent());
        self::assertFalse($this->makeStore()->isAtomic());
    }
}
