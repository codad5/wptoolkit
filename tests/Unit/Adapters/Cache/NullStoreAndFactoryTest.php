<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Cache;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Cache\CacheFactory;
use Codad5\WPToolkit\Adapters\Cache\NullStore;
use Codad5\WPToolkit\Adapters\Cache\ObjectCacheStore;
use Codad5\WPToolkit\Adapters\Cache\TransientStore;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Config;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\TestCase;

final class NullStoreAndFactoryTest extends TestCase
{
    public function test_null_store_stores_nothing_but_still_computes(): void
    {
        $store = new NullStore();

        self::assertFalse($store->set('key', 'value'));
        self::assertNull($store->get('key'));
        self::assertSame('computed', $store->remember('key', 60, fn () => 'computed'));
        self::assertSame(0, $store->count('hits'));
    }

    public function test_auto_uses_the_object_cache_only_when_it_is_persistent(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(true);
        self::assertInstanceOf(ObjectCacheStore::class, $this->factory()->make());
    }

    public function test_auto_falls_back_to_transients(): void
    {
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        self::assertInstanceOf(TransientStore::class, $this->factory()->make());
    }

    public function test_driver_comes_from_config_or_the_call(): void
    {
        self::assertInstanceOf(ArrayStore::class, $this->factory(['cache' => ['driver' => 'array']])->make());
        self::assertInstanceOf(NullStore::class, $this->factory()->make('default', 'null'));
    }

    public function test_unknown_driver_is_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->factory()->make('default', 'redis');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function factory(array $config = []): CacheFactory
    {
        return new CacheFactory(
            Config::fromArray('/p.php', ['slug' => 'my-plugin'] + $config),
            new Identity('my-plugin'),
            new FrozenClock()
        );
    }
}
