<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

use RuntimeException;

/**
 * An entry exists but could not be built.
 */
class ContainerException extends RuntimeException implements WPToolkitException
{
    public static function unresolvableParameter(string $parameter, string $owner): self
    {
        return new self(sprintf(
            'Cannot resolve parameter $%s of %s: it has no class type, no default value and was not passed explicitly.',
            $parameter,
            $owner
        ));
    }

    public static function notInstantiable(string $class): self
    {
        return new self(sprintf('%s is not instantiable (abstract class, interface or private constructor). Bind it to a concrete class or factory.', $class));
    }
}
