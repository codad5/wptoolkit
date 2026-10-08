<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * Where the release version comes from (Strategy). See Version for the built-in sources.
 */
interface VersionSource
{
    /**
     * @throws BuildException When the version can't be found.
     */
    public function resolve(Project $project, CommandRunner $runner): string;

    public function describe(): string;
}
