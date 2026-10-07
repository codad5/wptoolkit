<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\Logger;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Exposes a WPToolkit Logger as a PSR-3 LoggerInterface, for libraries that expect one.
 *
 * Only usable when the consumer installs `psr/log` (ADR-0012); never loaded otherwise.
 */
final class Psr3Bridge extends AbstractLogger
{
    public function __construct(private readonly Logger $logger)
    {
    }

    /**
     * @param mixed $level
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        /** @var array<string, mixed> $context */
        $this->logger->log(is_string($level) ? $level : 'info', $message, $context);
    }
}
