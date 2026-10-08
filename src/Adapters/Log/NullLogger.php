<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\LogLevel;

/**
 * Discards everything (Null Object): logging off without `if ($logger)` checks.
 */
final class NullLogger extends BaseLogger
{
    protected function write(LogLevel $level, string $message, array $context): void
    {
    }
}
