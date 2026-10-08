<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Container;

final class FixedClock implements Clock
{
    public function now(): int
    {
        return 1700000000;
    }
}
