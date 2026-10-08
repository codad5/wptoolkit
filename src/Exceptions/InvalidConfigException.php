<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

use InvalidArgumentException;

/**
 * Configuration passed to the library is missing or malformed.
 */
final class InvalidConfigException extends InvalidArgumentException implements WPToolkitException
{
}
