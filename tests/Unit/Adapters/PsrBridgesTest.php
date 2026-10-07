<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters;

use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Cache\Psr16Bridge;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Adapters\Log\BaseLogger;
use Codad5\WPToolkit\Adapters\Log\Psr3Bridge;
use Codad5\WPToolkit\Adapters\Log\PsrLoggerAdapter;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\Psr11Bridge;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\AbstractLogger;
use Psr\SimpleCache\InvalidArgumentException;
use Stringable;

final class PsrBridgesTest extends TestCase
{
    public function test_our_logger_works_as_a_psr3_logger(): void
    {
        $ours = new ArrayLogger();

        (new Psr3Bridge($ours))->warning('Disk at {pct}%', ['pct' => 91]);

        self::assertSame(['Disk at 91%'], $ours->messages());
    }

    public function test_a_psr3_logger_receives_redacted_records(): void
    {
        $psr = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };

        (new PsrLoggerAdapter($psr))->error('Auth failed', ['token' => 'abc']);

        self::assertSame(['error', 'Auth failed', ['token' => BaseLogger::REDACTED]], $psr->records[0]);
    }

    public function test_cache_works_as_psr16_including_ttl_and_multiples(): void
    {
        $clock = new FrozenClock();
        $cache = new Psr16Bridge(new ArrayStore($clock));

        $cache->setMultiple(['a' => 1, 'b' => false], 10);
        self::assertSame(['a' => 1, 'b' => false, 'c' => 'x'], $cache->getMultiple(['a', 'b', 'c'], 'x'));

        $clock->advance(11);
        self::assertFalse($cache->has('a'));

        $cache->set('gone', 1, 0);
        self::assertFalse($cache->has('gone'), 'a non-positive TTL deletes, per PSR-16');
    }

    public function test_psr16_reserved_characters_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Psr16Bridge(new ArrayStore(new FrozenClock())))->get('user:1');
    }

    public function test_container_works_as_psr11(): void
    {
        $psr = new Psr11Bridge(new Container());

        self::assertTrue($psr->has(FrozenClock::class));
        self::assertInstanceOf(FrozenClock::class, $psr->get(FrozenClock::class));

        $this->expectException(NotFoundExceptionInterface::class);
        $psr->get('missing.service');
    }
}
