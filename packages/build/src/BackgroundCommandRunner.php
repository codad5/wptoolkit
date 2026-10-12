<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * A runner that stays responsive while a command runs: it calls back several times a second, so the
 * build can draw a spinner and notice Ctrl+C, and it terminates the command if that callback throws.
 *
 * A separate interface so existing `CommandRunner` implementations keep working; with one of those
 * there is no spinner animation and an interruption is noticed when the command ends.
 */
interface BackgroundCommandRunner extends StreamingCommandRunner
{
    /**
     * @param Closure(string): void $onLine Each line of stdout or stderr, without the line ending.
     * @param Closure(): void $onTick Called several times a second while the command runs. If it
     *        throws, the command is terminated and the exception is rethrown.
     * @return array{exitCode: int, output: string}
     */
    public function watch(string $command, string $workingDirectory, Closure $onLine, Closure $onTick): array;
}
