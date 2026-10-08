<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Codad5\WPToolkit\Build\Patterns\PatternSource;

/**
 * Pattern files usable in `exclude()` / `include()` (the maintainer's idea, ADR-0015 / Track P):
 *
 *     ->exclude(Patterns::fromDistignore(), 'docs')
 *
 * Prefer `.distignore`: it lists exactly what not to ship. A `.gitignore` usually excludes build
 * output you *do* want to ship (`assets/dist`, `vendor`) and misses dev files you don't — pair it
 * with `include()` for what it wrongly drops.
 */
final class Patterns
{
    /**
     * WP-CLI's `.distignore` format (gitignore syntax).
     */
    public static function fromDistignore(string $file = '.distignore'): PatternSource
    {
        return self::lineFile($file);
    }

    public static function fromGitignore(string $file = '.gitignore'): PatternSource
    {
        return self::lineFile($file);
    }

    /**
     * Paths marked `export-ignore` in `.gitattributes` — what `git archive` leaves out.
     */
    public static function fromGitattributesExportIgnore(string $file = '.gitattributes'): PatternSource
    {
        return new class ($file) implements PatternSource {
            public function __construct(private readonly string $file)
            {
            }

            public function patterns(string $projectRoot): array
            {
                $patterns = [];
                foreach (Patterns::lines($projectRoot . '/' . $this->file) as $line) {
                    $parts = preg_split('/\s+/', $line) ?: [];
                    if (count($parts) > 1 && in_array('export-ignore', array_slice($parts, 1), true)) {
                        $patterns[] = $parts[0];
                    }
                }

                return $patterns;
            }

            public function describe(): string
            {
                return $this->file . ' (export-ignore)';
            }
        };
    }

    /**
     * Non-empty, non-comment lines of a file; [] when it doesn't exist.
     *
     * @internal
     * @return list<string>
     */
    public static function lines(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $lines = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '#')) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private static function lineFile(string $file): PatternSource
    {
        return new class ($file) implements PatternSource {
            public function __construct(private readonly string $file)
            {
            }

            public function patterns(string $projectRoot): array
            {
                return Patterns::lines($projectRoot . '/' . $this->file);
            }

            public function describe(): string
            {
                return $this->file;
            }
        };
    }
}
