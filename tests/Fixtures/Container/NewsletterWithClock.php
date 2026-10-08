<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Container;

final class NewsletterWithClock
{
    public function __construct(public Mailer $mailer, public ?Logger $audit = null)
    {
    }
}
