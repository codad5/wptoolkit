<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

use WP_CLI\Dispatcher\CommandNamespace;

/**
 * Inspect and maintain a plugin built on WPToolkit: toolkit:info, routes:list, hooks:list, migrate,
 * migrate:status, migrate:rollback and make:*.
 *
 * The `wp {slug}` container. Subcommands are added one by one with their full names, because WP-CLI's
 * `@subcommand` tag can't hold a colon. Only loaded under WP-CLI.
 */
final class ToolkitNamespace extends CommandNamespace
{
}
