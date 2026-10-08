<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * When a step runs. Steps run phase by phase, and in declaration order within a phase.
 */
enum Phase: int
{
    /** In the project directory, before staging — e.g. `npm run build`. */
    case Prepare = 1;

    /** In the staging copy — Composer, scoping, translations, version sync, custom steps. */
    case Transform = 2;

    /** On the pruned staging copy, before anything is written — checks that can fail the build. */
    case Verify = 3;
}
