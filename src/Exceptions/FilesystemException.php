<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

use RuntimeException;

/**
 * A filesystem operation failed, or a path was refused.
 */
final class FilesystemException extends RuntimeException implements WPToolkitException
{
    public static function unsafePath(string $path, string $reason): self
    {
        return new self(sprintf('Refused path "%s": %s.', $path, $reason));
    }

    public static function failed(string $operation, string $path): self
    {
        return new self(sprintf('Could not %s "%s".', $operation, $path));
    }

    public static function missing(string $path): self
    {
        return new self(sprintf('"%s" does not exist.', $path));
    }
}
