<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

/**
 * Nothing is registered under the id, and it is not a class that can be autowired.
 */
final class NotFoundException extends ContainerException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('No entry or class found for "%s". Register it with bind(), singleton() or instance().', $id));
    }
}
