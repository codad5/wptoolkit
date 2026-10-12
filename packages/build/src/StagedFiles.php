<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * What is in the staging directory once a build has pruned and verified it.
 */
final class StagedFiles
{
    /**
     * @param array<string, string> $files Relative path => absolute path, sorted (a deterministic order).
     * @param array<string, int> $sizes Top-level entry => bytes, largest first.
     */
    private function __construct(public readonly array $files, public readonly array $sizes, public readonly int $bytes)
    {
    }

    public static function of(string $staging): self
    {
        $files = [];
        $sizes = [];
        $bytes = 0;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($staging) + 1);
            $files[$relative] = $file->getPathname();
            $top = explode('/', $relative)[0] . (str_contains($relative, '/') ? '/' : '');
            $sizes[$top] = ($sizes[$top] ?? 0) + $file->getSize();
            $bytes += $file->getSize();
        }
        ksort($files, SORT_STRING);
        arsort($sizes);

        return new self($files, $sizes, $bytes);
    }
}
