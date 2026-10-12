<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * Runs commands through the system shell with proc_open, capturing stdout and stderr together.
 */
final class ProcessCommandRunner implements StreamingCommandRunner
{
    public function run(string $command, string $workingDirectory): array
    {
        return $this->stream($command, $workingDirectory, static function (string $line): void {
        });
    }

    public function stream(string $command, string $workingDirectory, Closure $onLine): array
    {
        // stderr joins stdout in the shell, so one pipe carries both and can't deadlock.
        $process = proc_open($command . ' 2>&1', [1 => ['pipe', 'w']], $pipes, $workingDirectory);

        if (!is_resource($process)) {
            return ['exitCode' => 127, 'output' => "Could not start: {$command}"];
        }

        $output = '';
        while (($line = fgets($pipes[1])) !== false) {
            $output .= $line;
            $onLine(rtrim($line, "\r\n"));
        }
        fclose($pipes[1]);

        return ['exitCode' => proc_close($process), 'output' => $output];
    }
}
