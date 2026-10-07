<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A view could not be found or failed while rendering.
 */
final class ViewException extends RuntimeException implements WPToolkitException
{
    /**
     * @param list<string> $searched
     */
    public static function notFound(string $view, array $searched): self
    {
        return new self(sprintf('View "%s" not found. Looked in: %s', $view, $searched === [] ? '(no view paths)' : implode(', ', $searched)));
    }

    public static function invalidName(string $view): self
    {
        return new self(sprintf('View name "%s" is invalid: use letters, digits, "-", "_", "." and "/" without "..".', $view));
    }

    public static function failed(string $view, Throwable $previous): self
    {
        return new self(sprintf('View "%s" failed: %s', $view, $previous->getMessage()), 0, $previous);
    }

    public static function misuse(string $message): self
    {
        return new self($message);
    }
}
