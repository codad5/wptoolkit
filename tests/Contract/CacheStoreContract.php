<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Contract;

use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * The behaviour every CacheStore must have (Phase 2.1). Each adapter's test extends this, so one
 * list of rules holds for every backend.
 */
abstract class CacheStoreContract extends TestCase
{
    protected FrozenClock $clock;

    abstract protected function makeStore(string $group = 'default'): CacheStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FrozenClock();
    }

    public function test_a_miss_returns_the_default(): void
    {
        $store = $this->makeStore();

        self::assertNull($store->get('missing'));
        self::assertSame('fallback', $store->get('missing', 'fallback'));
        self::assertFalse($store->has('missing'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function values(): iterable
    {
        yield 'string' => ['hello'];
        yield 'empty string' => [''];
        yield 'zero' => [0];
        yield 'false' => [false];
        yield 'null' => [null];
        yield 'array' => [['a' => 1, 'b' => [2, 3]]];
    }

    #[DataProvider('values')]
    public function test_every_value_round_trips_exactly(mixed $value): void
    {
        $store = $this->makeStore();

        self::assertTrue($store->set('key', $value));

        self::assertSame($value, $store->get('key', 'not-this'));
        self::assertTrue($store->has('key'), 'a cached falsy value is still a hit');
    }

    public function test_objects_round_trip(): void
    {
        $object = new stdClass();
        $object->name = 'x';
        $store = $this->makeStore();

        $store->set('object', $object);

        self::assertEquals($object, $store->get('object'));
    }

    public function test_delete_removes_an_entry(): void
    {
        $store = $this->makeStore();
        $store->set('key', 'value');

        $store->delete('key');

        self::assertFalse($store->has('key'));
    }

    public function test_entries_expire_after_their_ttl_and_not_before(): void
    {
        $store = $this->makeStore();
        $store->set('key', 'value', 10);

        $this->clock->advance(9);
        self::assertSame('value', $store->get('key'));

        $this->clock->advance(2);
        self::assertNull($store->get('key'));
    }

    public function test_a_null_ttl_never_expires(): void
    {
        $store = $this->makeStore();
        $store->set('key', 'value');

        $this->clock->advance(10 * 365 * 86400);

        self::assertSame('value', $store->get('key'));
    }

    public function test_remember_computes_once(): void
    {
        $store = $this->makeStore();
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;
            return false; // even a falsy result is remembered
        };

        $store->remember('key', 60, $compute);
        $result = $store->remember('key', 60, $compute);

        self::assertFalse($result);
        self::assertSame(1, $calls);
    }

    public function test_counters_count_and_start_at_zero(): void
    {
        $store = $this->makeStore();

        self::assertSame(0, $store->count('hits'));
        self::assertSame(1, $store->increment('hits'));
        self::assertSame(3, $store->increment('hits', 2));
        self::assertSame(3, $store->count('hits'));
    }

    public function test_a_counter_window_starts_when_it_is_created(): void
    {
        $store = $this->makeStore();
        $store->increment('hits', 1, 60);

        $this->clock->advance(50);
        $store->increment('hits', 1, 60); // must not push the expiry out
        self::assertSame(2, $store->count('hits'));

        $this->clock->advance(15);
        self::assertSame(0, $store->count('hits'));
    }

    public function test_counters_and_values_do_not_share_keys(): void
    {
        $store = $this->makeStore();
        $store->set('same', 'value');
        $store->increment('same');

        self::assertSame('value', $store->get('same'));
        self::assertSame(1, $store->count('same'));
    }

    public function test_clear_drops_values_and_counters_and_the_store_keeps_working(): void
    {
        $store = $this->makeStore();
        $store->set('key', 'value');
        $store->increment('hits');

        self::assertTrue($store->clear());

        self::assertFalse($store->has('key'));
        self::assertSame(0, $store->count('hits'));
        $store->set('key', 'new');
        self::assertSame('new', $store->get('key'));
    }

    public function test_groups_are_isolated(): void
    {
        $a = $this->makeStore('a');
        $b = $this->makeStore('b');

        $a->set('key', 'from a');
        $b->clear();

        self::assertFalse($b->has('key'));
        self::assertSame('from a', $a->get('key'));
    }
}
