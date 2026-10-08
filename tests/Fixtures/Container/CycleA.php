<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Container;

final class CycleA
{
    public function __construct(public CycleB $b)
    {
    }
}
