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
        private readonly Closure $output
    ) {
    }

    /**
     * Run a shell command, failing the step on a non-zero exit code.
     */
    public function exec(string $step, string $command, string $workingDirectory): string
    {
        $this->say("  $ {$command}");
        $result = $this->runner->run($command, $workingDirectory);
        if ($result['exitCode'] !== 0) {
            throw BuildException::inStep($step, sprintf(
                '"%s" exited with %d:%s%s',
                $command,
                $result['exitCode'],
                PHP_EOL,
                trim($result['output'])
            ));
        }

        return $result['output'];
    }

    public function say(string $line): void
    {
        ($this->output)($line);
    }

    public function warn(string $line): void
    {
        $this->warnings[] = $line;
        $this->say('  ! ' . $line);
    }

    /**
     * Absolute path inside the staging copy.
     */
    public function staged(string $relative = ''): string
    {
        return $relative === '' ? $this->staging : $this->staging . '/' . ltrim($relative, '/');
    }
}
