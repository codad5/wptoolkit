<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Steps;

use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\Phase;

/**
 * Shell commands in the project directory, before staging — e.g. `npm ci`, `npm run build`.
 */
final class RunCommands implements BuildStep
{
    /** @var list<string> */
    private readonly array $commands;

    /** True: always print these commands' output as they run. Null: follow `Build::verbose()`. */
    private ?bool $stream = null;

    public function __construct(string ...$commands)
    {
        $this->commands = array_values($commands);
    }

    /**
     * Commands whose output is printed as they run, whether or not the build is verbose.
     */
    public static function streamed(string ...$commands): self
    {
        $step = new self(...$commands);
        $step->stream = true;

        return $step;
    }

    public function name(): string
    {
        return 'run';
    }

    public function phase(): Phase
    {
        return Phase::Prepare;
    }

    public function run(BuildContext $context): void
    {
        foreach ($this->commands as $command) {
            $context->exec($this->name(), $command, $context->project->root, $this->stream);
        }
    }
}
