<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Support;

use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Cache\TransientStore;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Support\RateLimit\ClientIp;
use Codad5\WPToolkit\Support\RateLimit\RateLimiter;
use Codad5\WPToolkit\Tests\Support\FakeWordPressCache;
use Codad5\WPToolkit\Tests\TestCase;
use DateTimeImmutable;
use DateTimeZone;

final class RateLimitTest extends TestCase
{
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        // 10:00:00 exactly, so a 60s window starts now.
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-01-01 10:00:00', new DateTimeZone('UTC')));
        (new FakeWordPressCache($this->clock))->install();
    }

    /**
     * C3 regression: limits hold across requests, because the store persists (transients here).
     */
    public function test_allows_up_to_the_limit_then_blocks_across_requests(): void
    {
        $results = [];
        for ($request = 1; $request <= 4; $request++) {
            // A fresh limiter per "request": only the persistent store carries the count.
            $results[] = $this->persistentLimiter()->attempt('ip:1.2.3.4', 3, 60);
        }

        self::assertSame([true, true, true, false], array_map(static fn ($r) => $r->allowed, $results));
        self::assertSame(0, $results[3]->remaining);
        self::assertSame('60', $results[3]->headers()['Retry-After']);
    }

    public function test_retry_after_counts_down_to_the_next_window(): void
    {
        $limiter = $this->persistentLimiter();
        $limiter->attempt('k', 1, 60);

        $this->clock->advance(45);
        $blocked = $limiter->attempt('k', 1, 60);

        self::assertFalse($blocked->allowed);
        self::assertSame(15, $blocked->retryAfter);
    }

    public function test_the_next_window_starts_fresh(): void
    {
        $limiter = $this->persistentLimiter();
        $limiter->attempt('k', 1, 60);
        $limiter->attempt('k', 1, 60);

        $this->clock->advance(60);

        self::assertTrue($limiter->attempt('k', 1, 60)->allowed);
    }

    public function test_keys_have_separate_budgets(): void
    {
        $limiter = $this->persistentLimiter();
        $limiter->attempt('user:1', 1, 60);

        self::assertTrue($limiter->attempt('user:2', 1, 60)->allowed);
        self::assertSame(1, $limiter->used('user:1', 60));
    }

    public function test_a_non_persistent_store_is_reported_once_instead_of_failing_silently(): void
    {
        $logger = new ArrayLogger();
        $limiter = new RateLimiter(new ArrayStore($this->clock), $this->clock, $logger);

        $limiter->attempt('k', 5, 60);
        $limiter->attempt('k', 5, 60);

        self::assertCount(1, $logger->records);
        self::assertStringContainsString('does not persist', $logger->messages()[0]);
    }

    public function test_nonsensical_limits_are_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->persistentLimiter()->attempt('k', 0, 60);
    }

    public function test_client_ip_ignores_forwarded_headers_from_untrusted_clients(): void
    {
        $ip = new ClientIp(['10.0.0.1']);

        self::assertSame('203.0.113.9', $ip->resolve(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1']));
    }

    public function test_client_ip_reads_through_trusted_proxies_only(): void
    {
        $ip = new ClientIp(['10.0.0.1', '10.0.0.2']);

        self::assertSame('198.51.100.7', $ip->resolve([
            'REMOTE_ADDR' => '10.0.0.1',
            // Spoofed first entry from the client, then the real client, then our second proxy.
            'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7, 10.0.0.2',
        ]));
    }

    public function test_client_ip_falls_back_on_garbage(): void
    {
        self::assertSame('0.0.0.0', (new ClientIp())->resolve(['REMOTE_ADDR' => 'not-an-ip']));
    }

    private function persistentLimiter(): RateLimiter
    {
        return new RateLimiter(new TransientStore(new Identity('my-plugin'), 'rate', $this->clock), $this->clock);
    }
}
