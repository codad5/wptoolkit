<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * What a step can see and use while a build runs.
 */
final class BuildContext
{
    /** @var list<string> */
    public array $warnings = [];

    /**
     * @param Closure(string): void $output
     */
    public function __construct(
        public readonly Project $project,
        public readonly string $staging,
        public readonly string $version,
        public readonly CommandRunner $runner,
        private readonly Closure $output,
        public readonly bool $verbose = false,
        public readonly bool $quiet = false
    ) {
    }

    /** How much of a streamed command's output a failure repeats: it was already printed once. */
    private const FAILURE_TAIL = 20;

    /**
     * Run a shell command, failing the step on a non-zero exit code.
     *
     * @param bool|null $stream Print the command's output as it runs. Null follows `Build::verbose()`.
     */
    public function exec(string $step, string $command, string $workingDirectory, ?bool $stream = null): string
    {
        $stream ??= $this->verbose;
        $this->say("  $ {$command}");

        $print = function (string $line): void {
            $this->say('    | ' . $line);
        };
        if ($stream && $this->runner instanceof StreamingCommandRunner) {
            $result = $this->runner->stream($command, $workingDirectory, $print);
        } else {
            $result = $this->runner->run($command, $workingDirectory);
            if ($stream) {
                // This runner can't stream: show the output now that the command has ended.
                array_map($print, self::lines($result['output']));
            }
        }

        if ($result['exitCode'] !== 0) {
            $lines = self::lines($result['output']);
            $shown = $stream ? array_slice($lines, -self::FAILURE_TAIL) : $lines;
            throw BuildException::inStep($step, sprintf(
                '"%s" exited with %d:%s%s%s',
                $command,
                $result['exitCode'],
                PHP_EOL,
                count($shown) < count($lines) ? sprintf('(last %d lines)%s', count($shown), PHP_EOL) : '',
                implode(PHP_EOL, $shown)
            ));
        }

        return $result['output'];
    }

    /**
     * @return list<string>
     */
    private static function lines(string $output): array
    {
        $output = trim(str_replace("\r\n", "\n", $output));

        return $output === '' ? [] : explode("\n", $output);
    }

    /**
     * Progress output. Nothing is printed for a quiet build.
     */
    public function say(string $line): void
    {
        if (!$this->quiet) {
            ($this->output)($line);
        }
    }

    /**
     * Something a person should read. Recorded on the report and printed even for a quiet build.
     */
    public function warn(string $line): void
    {
        $this->warnings[] = $line;
        ($this->output)('  ! ' . $line);
    }

    /**
     * Absolute path inside the staging copy.
     */
    public function staged(string $relative = ''): string
    {
        return $relative === '' ? $this->staging : $this->staging . '/' . ltrim($relative, '/');
    }
}
