<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Container;

final class CycleB
{
    public function __construct(public CycleA $a)
    {
    }
}
