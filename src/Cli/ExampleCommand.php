<?php

/**
 * ExampleCommand.php
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://example.com/plugin-name
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

use WP_CLI;

/**
 * Implements WP-CLI example command.
 */
class ExampleCommand
{
    /**
     * Prints a greeting.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the person to greet.
     *
     * ## EXAMPLES
     *
     *     wp example hello Newman
     *
     * @when after_wp_load
     *
     * @param list<string> $args
     */
    public function hello(array $args): void
    {
        // Print the message.
        WP_CLI::error(sprintf('Hello, %1$s!', $args[0]));
    }
}
