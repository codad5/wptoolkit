<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Filesystem;

use Codad5\WPToolkit\Contracts\Filesystem\Filesystem;
use Codad5\WPToolkit\Exceptions\FilesystemException;
use WP_Filesystem_Base;

/**
 * Files through WordPress's WP_Filesystem — the method WordPress chose for this site (direct, FTP
 * or SSH). Use it where a plugin writes outside uploads on hosts that need credentials.
 *
 * The WP_Filesystem object is resolved lazily, so constructing this adapter is free.
 */
final class WpFilesystem implements Filesystem
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    public function read(string $path): string
    {
        $contents = $this->fs()->get_contents($this->absolute($path));

        return is_string($contents) ? $contents : throw FilesystemException::missing($path);
    }

    public function write(string $path, string $contents): void
    {
        $absolute = $this->absolute($path);
        $this->makeDirectoryAbsolute(dirname($absolute), $path);

        $mode = defined('FS_CHMOD_FILE') ? (int) constant('FS_CHMOD_FILE') : 0644;
        if (!$this->fs()->put_contents($absolute, $contents, $mode)) {
            throw FilesystemException::failed('write', $path);
        }
    }

    public function exists(string $path): bool
    {
        return $this->fs()->exists($this->absolute($path));
    }

    public function isFile(string $path): bool
    {
        return $this->fs()->is_file($this->absolute($path));
    }

    public function isDirectory(string $path): bool
    {
        return $this->fs()->is_dir($this->absolute($path));
    }

    public function delete(string $path): void
    {
        $absolute = $this->absolute($path);
        if ($this->fs()->is_file($absolute) && !$this->fs()->delete($absolute, false, 'f')) {
            throw FilesystemException::failed('delete', $path);
        }
    }

    public function makeDirectory(string $path): void
    {
        $this->makeDirectoryAbsolute($this->absolute($path), $path);
    }

    public function deleteDirectory(string $path): void
    {
        $absolute = $this->absolute($path);
        if ($absolute === $this->root) {
            foreach ($this->dirlist($absolute) as $name => $entry) {
                $this->fs()->delete($absolute . '/' . $name, true, ($entry['type'] ?? 'f') === 'd' ? 'd' : 'f');
            }
            return;
        }
        if ($this->fs()->is_dir($absolute) && !$this->fs()->delete($absolute, true, 'd')) {
            throw FilesystemException::failed('delete', $path);
        }
    }

    public function copy(string $from, string $to): void
    {
        $target = $this->absolute($to);
        $this->makeDirectoryAbsolute(dirname($target), $to);
        if (!$this->fs()->copy($this->absolute($from), $target, true)) {
            throw FilesystemException::failed('copy to', $to);
        }
    }

    public function move(string $from, string $to): void
    {
        $target = $this->absolute($to);
        $this->makeDirectoryAbsolute(dirname($target), $to);
        if (!$this->fs()->move($this->absolute($from), $target, true)) {
            throw FilesystemException::failed('move to', $to);
        }
    }

    public function size(string $path): int
    {
        $size = $this->fs()->size($this->absolute($path));

        return is_int($size) ? $size : throw FilesystemException::missing($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $base = PathGuard::normalize($directory);
        $found = [];
        $this->collect($this->absolute($directory), $base, $recursive, $found);
        sort($found);

        return $found;
    }

    /**
     * @param list<string> $found
     */
    private function collect(string $absolute, string $relative, bool $recursive, array &$found): void
    {
        foreach ($this->dirlist($absolute) as $name => $entry) {
            $path = ltrim($relative . '/' . $name, '/');
            if (($entry['type'] ?? 'f') === 'f') {
                $found[] = $path;
            } elseif ($recursive) {
                $this->collect($absolute . '/' . $name, $path, true, $found);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function dirlist(string $absolute): array
    {
        $list = $this->fs()->dirlist($absolute, true, false);

        return is_array($list) ? $list : [];
    }

    private function makeDirectoryAbsolute(string $absolute, string $path): void
    {
        if ($this->fs()->is_dir($absolute)) {
            return;
        }

        $this->makeDirectoryAbsolute(dirname($absolute), $path);
        if (!$this->fs()->mkdir($absolute) && !$this->fs()->is_dir($absolute)) {
            throw FilesystemException::failed('create directory for', $path);
        }
    }

    private function absolute(string $path): string
    {
        $relative = PathGuard::normalize($path);

        return $relative === '' ? $this->root : $this->root . '/' . $relative;
    }

    private function fs(): WP_Filesystem_Base
    {
        $current = $GLOBALS['wp_filesystem'] ?? null;
        if ($current instanceof WP_Filesystem_Base) {
            return $current;
        }

        if (!function_exists('WP_Filesystem') && defined('ABSPATH')) {
            require_once constant('ABSPATH') . 'wp-admin/includes/file.php';
        }
        if (function_exists('WP_Filesystem')) {
            WP_Filesystem();
        }

        // WP_Filesystem() fills the global; read it again.
        $initialised = $GLOBALS['wp_filesystem'] ?? null;
        if (!$initialised instanceof WP_Filesystem_Base) {
            throw new FilesystemException('WordPress could not initialise WP_Filesystem (credentials may be required).');
        }

        return $initialised;
    }
}
