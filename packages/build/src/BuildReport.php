<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * What a finished build produced.
 */
final class BuildReport
{
    /**
     * @param string $zip The zip written, or '' for a directory build.
     * @param string $sha256 The zip's checksum, or '' for a directory build.
     * @param list<string> $files Paths inside the zip (under its top-level folder), or relative to the
     *        directory for a directory build; sorted.
     * @param array<string, int> $largestDirectories Top-level directory => bytes, largest first.
     * @param list<string> $warnings
     * @param string $directory The folder written by `toDirectory()`, or '' for a zip build.
     */
    public function __construct(
        public readonly string $zip,
        public readonly string $sha256,
        public readonly string $version,
        public readonly int $bytes,
        public readonly array $files,
        public readonly array $largestDirectories,
        public readonly array $warnings,
        public readonly string $directory = ''
    ) {
    }

    /**
     * What `zip()` and `toDirectory()` print when they finish; you don't need to echo it yourself.
     */
    public function summary(): string
    {
        $name = $this->directory !== '' ? basename($this->directory) . '/' : basename($this->zip);
        $lines = [sprintf('%s  %s, %d files, %.1f KB', $name, $this->version, count($this->files), $this->bytes / 1024)];
        foreach (array_slice($this->largestDirectories, 0, 5, true) as $directory => $bytes) {
            $lines[] = sprintf('  %-30s %8.1f KB', $directory, $bytes / 1024);
        }
        if ($this->sha256 !== '') {
            $lines[] = 'sha256 ' . $this->sha256;
        }

        return implode(PHP_EOL, $lines);
    }
}
