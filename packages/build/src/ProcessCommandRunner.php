<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;
use Throwable;

/**
 * Runs commands through the system shell with proc_open, capturing stdout and stderr together.
 *
 * The command writes to a temporary file that this class polls, rather than to a pipe: reading a
 * pipe blocks until the command says something (and PHP can't make a child's pipe non-blocking on
 * Windows), which would freeze the spinner and hide Ctrl+C for as long as the command is silent.
 */
final class ProcessCommandRunner implements BackgroundCommandRunner
{
    /** Microseconds between polls: short at first so quick commands stay quick, then relaxed. */
    private const POLL_FROM = 5_000;
    private const POLL_UP_TO = 100_000;

    public function run(string $command, string $workingDirectory): array
    {
        return $this->watch($command, $workingDirectory, static function (string $line): void {
        }, static function (): void {
        });
    }

    public function stream(string $command, string $workingDirectory, Closure $onLine): array
    {
        return $this->watch($command, $workingDirectory, $onLine, static function (): void {
        });
    }

    public function watch(string $command, string $workingDirectory, Closure $onLine, Closure $onTick): array
    {
        $log = tempnam(sys_get_temp_dir(), 'wptk');
        if ($log === false) {
            return ['exitCode' => 127, 'output' => "Could not start: {$command} (no temporary file)"];
        }

        // The shell does the redirecting (rather than a descriptor handed to proc_open), so it doesn't
        // depend on which handles this process happens to have open. stderr joins stdout, so one file
        // carries both in the order they were written.
        $process = proc_open($command . ' > ' . escapeshellarg($log) . ' 2>&1', [], $pipes, $workingDirectory);
        if (!is_resource($process)) {
            @unlink($log);
            return ['exitCode' => 127, 'output' => "Could not start: {$command}"];
        }

        $output = '';
        $pending = '';
        $read = 0;
        $drain = static function () use ($log, &$output, &$pending, &$read, $onLine): void {
            $chunk = (string) file_get_contents($log, false, null, $read);
            if ($chunk === '') {
                return;
            }
            $read += strlen($chunk);
            $output .= $chunk;
            $lines = explode("\n", $pending . $chunk);
            $pending = (string) array_pop($lines);
            foreach ($lines as $line) {
                $onLine(rtrim($line, "\r"));
            }
        };

        $wait = self::POLL_FROM;
        try {
            do {
                $status = proc_get_status($process);
                $drain();
                if ($status['running']) {
                    $onTick();
                    usleep($wait);
                    $wait = min(self::POLL_UP_TO, $wait * 2);
                }
            } while ($status['running']);

            $drain();
            if ($pending !== '') {
                $onLine(rtrim($pending, "\r"));
            }

            return ['exitCode' => (int) $status['exitcode'], 'output' => $output];
        } catch (Throwable $stop) {
            self::terminate($process);
            throw $stop;
        } finally {
            proc_close($process);
            @unlink($log);
        }
    }

    /**
     * Stop the command and what it started. proc_terminate() alone ends only the shell: on Windows
     * `npm run build` would leave node running.
     *
     * @param resource $process
     */
    private static function terminate(mixed $process): void
    {
        $status = proc_get_status($process);
        if (!$status['running']) {
            return;
        }

        $pid = (int) $status['pid'];
        if (PHP_OS_FAMILY === 'Windows') {
            exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
        } else {
            exec('pkill -TERM -P ' . $pid . ' 2>/dev/null');
        }
        proc_terminate($process);

        // Give it a moment to go, so its files aren't still open when the caller cleans up.
        for ($i = 0; $i < 50 && proc_get_status($process)['running']; $i++) {
            usleep(20_000);
        }
    }
}
