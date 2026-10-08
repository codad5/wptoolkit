<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Container;

final class NeedsScalar
{
    public function __construct(public string $apiKey)
    {
    }
}
