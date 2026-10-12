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
    /** How much of a streamed command's output a failure repeats: it was already printed once. */
    private const FAILURE_TAIL = 20;

    /** @var list<string> */
    public array $warnings = [];

    private readonly Progress $progress;

    /**
     * @param Closure(string): void $output
     * @param Progress|null $progress How output is drawn; plain lines to `$output` when omitted.
     * @param Interruption|null $interruption Checked at safe points; the build stops when it fires.
     */
    public function __construct(
        public readonly Project $project,
        public readonly string $staging,
        public readonly string $version,
        public readonly CommandRunner $runner,
        Closure $output,
        public readonly bool $verbose = false,
        public readonly bool $quiet = false,
        ?Progress $progress = null,
        private readonly ?Interruption $interruption = null
    ) {
        $this->progress = $progress ?? new Progress($output, quiet: $quiet);
    }

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
        // A streamed command's own lines are the progress; a silent one gets the spinner.
        $this->progress->activity($stream ? null : $command);
        try {
            $result = $this->run($command, $workingDirectory, $stream, $print);
        } finally {
            $this->progress->activity(null);
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
     * A safe point: stop here if the build was interrupted, and keep the spinner moving. Long loops
     * in a step (one iteration per file, say) should call it.
     *
     * @throws BuildException When the build has been asked to stop.
     */
    public function checkpoint(): void
    {
        $this->interruption?->check();
        $this->progress->tick();
    }

    public function progress(): Progress
    {
        return $this->progress;
    }

    /**
     * Progress output. Nothing is printed for a quiet build.
     */
    public function say(string $line): void
    {
        $this->progress->line($line);
    }

    /**
     * Something a person should read. Recorded on the report and printed even for a quiet build.
     */
    public function warn(string $line): void
    {
        $this->warnings[] = $line;
        $this->progress->warn($line);
    }

    /**
     * Absolute path inside the staging copy.
     */
    public function staged(string $relative = ''): string
    {
        return $relative === '' ? $this->staging : $this->staging . '/' . ltrim($relative, '/');
    }

    /**
     * @param Closure(string): void $print
     * @return array{exitCode: int, output: string}
     */
    private function run(string $command, string $workingDirectory, bool $stream, Closure $print): array
    {
        $quietly = static function (string $line): void {
        };

        if ($this->runner instanceof BackgroundCommandRunner) {
            return $this->runner->watch($command, $workingDirectory, $stream ? $print : $quietly, fn () => $this->checkpoint());
        }
        if ($stream && $this->runner instanceof StreamingCommandRunner) {
            return $this->runner->stream($command, $workingDirectory, $print);
        }

        $result = $this->runner->run($command, $workingDirectory);
        if ($stream) {
            // This runner can't stream: show the output now that the command has ended.
            array_map($print, self::lines($result['output']));
        }
        $this->checkpoint();

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $output): array
    {
        $output = trim(str_replace("\r\n", "\n", $output));

        return $output === '' ? [] : explode("\n", $output);
    }
}
