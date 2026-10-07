<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Contracts\Log\LogLevel;
use Stringable;

/**
 * Sends every record to several loggers, each applying its own minimum level.
 */
final class StackLogger extends BaseLogger
{
    /** @var list<Logger> */
    private readonly array $loggers;

    public function __construct(Logger ...$loggers)
    {
        parent::__construct(LogLevel::Debug);
        $this->loggers = array_values($loggers);
    }

    public function log(LogLevel|string $level, string|Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }

    protected function write(LogLevel $level, string $message, array $context): void
    {
        // Not used: log() delegates the raw record so each logger interpolates and filters itself.
    }
}
