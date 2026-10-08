<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Cache;

use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Tests\Contract\CacheStoreContract;

final class ArrayStoreTest extends CacheStoreContract
{
    /** @var array<string, ArrayStore> */
    private array $stores = [];

    protected function makeStore(string $group = 'default'): CacheStore
    {
        // An array store is per instance; "groups" are separate instances.
        return $this->stores[$group] ??= new ArrayStore($this->clock);
    }

    public function test_is_not_persistent(): void
    {
        self::assertFalse($this->makeStore()->isPersistent());
    }
}
