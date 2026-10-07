<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Providers;

final class Uninstaller
{
    public static function uninstall(): void
    {
        EventLog::$events[] = 'uninstall';
    }
}
