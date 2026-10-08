<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

use WP_CLI;

/**
 * Console output through WP-CLI (colours, --format, exit codes).
 */
final class WpCliConsole implements Console
{
    public function line(string $message): void
    {
        WP_CLI::line($message);
    }

    public function success(string $message): void
    {
        WP_CLI::success($message);
    }

    public function warning(string $message): void
    {
        WP_CLI::warning($message);
    }

    public function error(string $message): never
    {
        WP_CLI::error($message);
        throw new CliException($message); // unreachable: WP_CLI::error() exits
    }

    public function table(array $rows, array $columns, string $format = 'table'): void
    {
        WP_CLI\Utils\format_items($format, $rows, $columns);
    }
}
