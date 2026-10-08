<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Clock;

use Codad5\WPToolkit\Contracts\Clock\Clock;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A clock that only moves when told to. For tests, and for code that must use one consistent
 * "now" across a whole operation.
 */
final class FrozenClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(?DateTimeImmutable $now = null)
    {
        $this->now = $now ?? new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('%+d seconds', $seconds));
    }

    public function set(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }
}
