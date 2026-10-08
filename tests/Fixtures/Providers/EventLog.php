<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Providers;

/**
 * Records lifecycle events in order, so tests can assert sequencing.
 */
final class EventLog
{
    /** @var list<string> */
    public static array $events = [];
}
