<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * One unit of a build (Command pattern). Add your own with `Build::step(new MyStep())`.
 */
interface BuildStep
{
    /**
     * Shown in progress output and in error messages.
     */
    public function name(): string;

    public function phase(): Phase;

    /**
     * Do the work. Throw BuildException to stop the build with a reason.
     */
    public function run(BuildContext $context): void;
}
