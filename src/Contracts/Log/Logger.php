<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Log;

use Stringable;

/**
 * A logger shaped like PSR-3 without depending on it (ADR-0004, ADR-0012): the same eight methods,
 * `{placeholder}` interpolation from the context, and an `exception` context key for Throwables.
 */
interface Logger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(LogLevel|string $level, string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function emergency(string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function alert(string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function critical(string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function warning(string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function notice(string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function info(string|Stringable $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function debug(string|Stringable $message, array $context = []): void;
}
