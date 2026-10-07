<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\LogLevel;

/**
 * Sends records to Query Monitor's Logs panel through its `qm/{level}` actions. Harmless when Query
 * Monitor isn't active: nothing listens to those actions.
 */
final class QueryMonitorLogger extends BaseLogger
{
    public function __construct(private readonly string $channel, LogLevel $minimum = LogLevel::Debug)
    {
        parent::__construct($minimum);
    }

    protected function write(LogLevel $level, string $message, array $context): void
    {
        $exception = $context['exception'] ?? null;
        if ($exception instanceof \Throwable) {
            do_action('qm/' . $level->value, $exception);
        }

        do_action('qm/' . $level->value, '[' . $this->channel . '] ' . $message);
    }
}
