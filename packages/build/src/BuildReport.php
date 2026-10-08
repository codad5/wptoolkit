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
     * @param list<string> $files Paths inside the zip (under the top-level folder), sorted.
     * @param array<string, int> $largestDirectories Top-level directory => bytes, largest first.
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly string $zip,
        public readonly string $sha256,
        public readonly string $version,
        public readonly int $bytes,
        public readonly array $files,
        public readonly array $largestDirectories,
        public readonly array $warnings
    ) {
    }

    public function summary(): string
    {
        $lines = [sprintf('%s  %s, %d files, %.1f KB', basename($this->zip), $this->version, count($this->files), $this->bytes / 1024)];
        foreach (array_slice($this->largestDirectories, 0, 5, true) as $directory => $bytes) {
            $lines[] = sprintf('  %-30s %8.1f KB', $directory, $bytes / 1024);
        }
        $lines[] = 'sha256 ' . $this->sha256;

        return implode(PHP_EOL, $lines);
    }
}
