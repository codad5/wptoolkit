<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

use LogicException;

/**
 * Something was done at the wrong point in the application's lifecycle.
 */
final class LifecycleException extends LogicException implements WPToolkitException
{
}
