<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Providers;

use Codad5\WPToolkit\Foundation\ServiceProvider;
use Codad5\WPToolkit\Tests\Fixtures\Container\Logger;

final class SecondProvider extends ServiceProvider
{
    public function register(): void
    {
        EventLog::$events[] = 'register:second';
    }

    public function boot(Logger $logger): void
    {
        EventLog::$events[] = 'boot:second';
        EventLog::$events[] = 'boot:second:' . $logger::class;
    }
}
