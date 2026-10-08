<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Cache;

use Codad5\WPToolkit\Adapters\Cache\TransientStore;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Contract\CacheStoreContract;
use Codad5\WPToolkit\Tests\Support\FakeWordPressCache;

final class TransientStoreTest extends CacheStoreContract
{
    private FakeWordPressCache $wordpress;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wordpress = new FakeWordPressCache($this->clock);
        $this->wordpress->install();
    }

    protected function makeStore(string $group = 'default'): CacheStore
    {
        return new TransientStore(new Identity('my-plugin'), $group, $this->clock);
    }

    public function test_is_persistent_but_not_atomic(): void
    {
        self::assertTrue($this->makeStore()->isPersistent());
        self::assertFalse($this->makeStore()->isAtomic());
    }

    public function test_every_transient_name_is_prefixed_by_the_consumer_and_fits_wordpress(): void
    {
        $this->makeStore()->set(str_repeat('long-key', 40), 'value');

        foreach (array_keys($this->wordpress->transients) as $name) {
            self::assertStringStartsWith('my_plugin_', $name);
            self::assertLessThanOrEqual(172, strlen($name));
        }
    }

    public function test_clear_writes_one_counter_and_scans_nothing(): void
    {
        $this->makeStore('a')->set('key', 'value');
        $before = count($this->wordpress->transients);

        $this->makeStore('a')->clear();

        self::assertSame($before + 1, count($this->wordpress->transients));
    }
}
