<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Uses any PSR-3 logger (Monolog, a host's logging library…) as a WPToolkit Logger. Redaction and
 * interpolation still happen here first, so secrets never reach the PSR logger.
 *
 * Only usable when the consumer installs `psr/log` (ADR-0012).
 */
final class PsrLoggerAdapter extends BaseLogger
{
    public function __construct(private readonly LoggerInterface $psr, LogLevel $minimum = LogLevel::Debug)
    {
        parent::__construct($minimum);
    }

    protected function write(LogLevel $level, string $message, array $context): void
    {
        $this->psr->log($level->value, $message, $context);
    }
}
