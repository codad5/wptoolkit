<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Filesystem;

use Codad5\WPToolkit\Exceptions\FilesystemException;

/**
 * Turns a user- or code-supplied relative path into a safe, normalised one, or refuses it.
 *
 * Refused: null bytes, stream wrappers (`phar://`, `php://` …), absolute paths (`/x`, `C:\x`,
 * `\\server\share`) and any `..` that would climb above the root. Purely lexical — no filesystem
 * access — so it behaves the same for every adapter.
 */
final class PathGuard
{
    /**
     * @return string The path with `/` separators, no `.` segments, no leading or trailing slash;
     *                '' for the root itself.
     * @throws FilesystemException
     */
    public static function normalize(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw FilesystemException::unsafePath($path, 'contains a null byte');
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            throw FilesystemException::unsafePath($path, 'stream wrappers are not allowed');
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/') || preg_match('#^[a-z]:#i', $path) === 1) {
            throw FilesystemException::unsafePath($path, 'must be relative to the root');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    throw FilesystemException::unsafePath($path, 'climbs above the root');
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
