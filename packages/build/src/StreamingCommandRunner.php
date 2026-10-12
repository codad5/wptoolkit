<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * A runner that can hand over a command's output while it is still running (`Build::verbose()`).
 * A separate interface so existing `CommandRunner` implementations keep working: with one of those,
 * verbose output is printed when the command ends.
 */
interface StreamingCommandRunner extends CommandRunner
{
    /**
     * @param Closure(string): void $onLine Called once per line of stdout or stderr, without the line ending.
     * @return array{exitCode: int, output: string} The whole output is still returned.
     */
    public function stream(string $command, string $workingDirectory, Closure $onLine): array;
}
