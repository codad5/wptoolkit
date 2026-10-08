<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

/**
 * Resolving an entry required itself, directly or through other entries.
 */
final class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $chain Ids being resolved, in order, ending with the repeated one.
     */
    public static function forChain(array $chain): self
    {
        return new self('Circular dependency: ' . implode(' -> ', $chain));
    }
}
