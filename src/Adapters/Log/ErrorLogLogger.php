<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\LogLevel;

/**
 * Writes one line per record to PHP's error log (wp-content/debug.log with WP_DEBUG_LOG):
 * `[my-plugin] WARNING: Message {"key":"value"}`.
 */
final class ErrorLogLogger extends BaseLogger
{
    public function __construct(private readonly string $channel, LogLevel $minimum = LogLevel::Debug)
    {
        parent::__construct($minimum);
    }

    protected function write(LogLevel $level, string $message, array $context): void
    {
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        $line = sprintf('[%s] %s: %s', $this->channel, strtoupper($level->value), $message);
        if ($context !== []) {
            $line .= ' ' . json_encode(
                array_map([BaseLogger::class, 'stringify'], $context),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        }
        if ($exception !== null) {
            $line .= ' | ' . self::stringify($exception);
        }

        error_log($line);
    }
}
