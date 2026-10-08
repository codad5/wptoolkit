<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Container;

final class Mailer
{
    public function __construct(public Logger $logger, public string $from = 'noreply@example.com')
    {
    }
}
