<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use RuntimeException;

/**
 * A build step failed, or verification refused the result. The message says which and why.
 */
final class BuildException extends RuntimeException
{
    public static function inStep(string $step, string $reason): self
    {
        return new self(sprintf('[%s] %s', $step, $reason));
    }
}
