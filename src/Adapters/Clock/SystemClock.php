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
 * The real time, in UTC (WordPress stores and expires everything in UTC).
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
