<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * Runs shell commands for steps such as `run()` and `composer()`. Injectable, so builds can be
 * tested without npm or Composer.
 */
interface CommandRunner
{
    /**
     * @return array{exitCode: int, output: string}
     */
    public function run(string $command, string $workingDirectory): array;
}
