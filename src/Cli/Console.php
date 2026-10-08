<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

/**
 * Where commands write. WpCliConsole prints through WP-CLI; BufferedConsole collects output for
 * tests. error() never returns.
 */
interface Console
{
    public function line(string $message): void;

    public function success(string $message): void;

    public function warning(string $message): void;

    /**
     * @throws CliException Always — WP-CLI exits instead.
     */
    public function error(string $message): never;

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<string> $columns
     */
    public function table(array $rows, array $columns, string $format = 'table'): void;
}
