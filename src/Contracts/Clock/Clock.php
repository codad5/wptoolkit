<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Clock;

use DateTimeImmutable;

/**
 * The current time, injectable so expiry and rate windows are testable (ADR-0004).
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
