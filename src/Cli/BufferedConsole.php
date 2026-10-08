<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

/**
 * Collects command output in memory — for tests, and for running a command from code.
 */
final class BufferedConsole implements Console
{
    /** @var list<string> */
    public array $lines = [];

    /** @var list<array{rows: list<array<string, scalar|null>>, columns: list<string>}> */
    public array $tables = [];

    public function line(string $message): void
    {
        $this->lines[] = $message;
    }

    public function success(string $message): void
    {
        $this->lines[] = 'Success: ' . $message;
    }

    public function warning(string $message): void
    {
        $this->lines[] = 'Warning: ' . $message;
    }

    public function error(string $message): never
    {
        throw new CliException($message);
    }

    public function table(array $rows, array $columns, string $format = 'table'): void
    {
        $this->tables[] = ['rows' => $rows, 'columns' => $columns];
    }

    public function output(): string
    {
        return implode("\n", $this->lines);
    }
}
