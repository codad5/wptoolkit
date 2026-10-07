<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Providers;

use Codad5\WPToolkit\Foundation\ServiceProvider;

final class FirstProvider extends ServiceProvider
{
    public function register(): void
    {
        EventLog::$events[] = 'register:first';
    }

    public function boot(): void
    {
        EventLog::$events[] = 'boot:first';
    }
}
