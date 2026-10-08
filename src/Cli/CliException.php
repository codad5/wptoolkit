<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

use Codad5\WPToolkit\Exceptions\WPToolkitException;
use RuntimeException;

/**
 * A command failed; the message is for the person at the terminal.
 */
final class CliException extends RuntimeException implements WPToolkitException
{
}
