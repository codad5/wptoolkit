<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Container;

final class Newsletter
{
    public function __construct(public Mailer $mailer, public Clock $clock, public ?Logger $audit = null)
    {
    }
}
