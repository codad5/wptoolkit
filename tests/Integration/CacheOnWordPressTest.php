<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Cache\ObjectCacheStore;
use Codad5\WPToolkit\Adapters\Cache\TransientStore;
use Codad5\WPToolkit\Adapters\Clock\SystemClock;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Support\RateLimit\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The cache adapters against WordPress's real transients (database) and object cache.
 */
final class CacheOnWordPressTest extends TestCase
{
    private string $group;

    protected function setUp(): void
    {
        $this->group = 'it-' . uniqid();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function stores(): iterable
    {
        yield 'transients' => ['transient'];
        yield 'object cache' => ['object'];
    }

    #[DataProvider('stores')]
    public function test_falsy_values_round_trip_through_real_wordpress(string $kind): void
    {
        $store = $this->store($kind);

        foreach (['false' => false, 'null' => null, 'zero' => 0, 'empty' => '', 'array' => ['a' => 1]] as $key => $value) {
            $store->set($key, $value, 60);
            self::assertSame($value, $store->get($key, 'miss'), $key);
            self::assertTrue($store->has($key), $key);
        }
    }

    #[DataProvider('stores')]
    public function test_counters_clear_and_groups(string $kind): void
    {
        $store = $this->store($kind);
        $other = $this->store($kind, $this->group . '-other');

        self::assertSame(1, $store->increment('hits', 1, 60));
        self::assertSame(2, $store->increment('hits', 1, 60));
        $store->set('key', 'value');
        $other->set('key', 'other');

        $store->clear();

        self::assertSame(0, $store->count('hits'));
        self::assertFalse($store->has('key'));
        self::assertSame('other', $other->get('key'));
    }

    public function test_transients_really_expire(): void
    {
        $store = $this->store('transient');
        $store->set('short', 'lived', 1);

        sleep(2);

        self::assertNull($store->get('short'));
    }

    public function test_transient_names_are_prefixed_and_stored_in_wordpress(): void
    {
        $this->store('transient')->set('visible', 'yes');

        global $wpdb;
        $found = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_wptoolkit_it_') . '%'
        ));

        self::assertGreaterThan(0, $found);
    }

    public function test_rate_limits_hold_across_separate_limiter_instances(): void
    {
        $results = [];
        for ($request = 0; $request < 3; $request++) {
            $limiter = new RateLimiter($this->store('transient'), new SystemClock());
            $results[] = $limiter->attempt('ip:203.0.113.5', 2, 3600)->allowed;
        }

        self::assertSame([true, true, false], $results);
    }

    private function store(string $kind, ?string $group = null): CacheStore
    {
        $identity = new Identity('wptoolkit-it');

        return $kind === 'transient'
            ? new TransientStore($identity, $group ?? $this->group)
            : new ObjectCacheStore($identity, $group ?? $this->group);
    }
}
