<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Filesystem;

use Codad5\WPToolkit\Contracts\Filesystem\Filesystem;
use Codad5\WPToolkit\Exceptions\FilesystemException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Files on local disk under a root directory, with PHP's own functions — what WordPress's "direct"
 * filesystem method does. Use WpFilesystem where WordPress may need FTP/SSH credentials.
 *
 * Symlinks are followed only when they stay inside the root.
 */
final class LocalFilesystem implements Filesystem
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw FilesystemException::missing($root);
        }

        $this->root = rtrim(str_replace('\\', '/', $real), '/');
    }

    public function read(string $path): string
    {
        $absolute = $this->absolute($path);
        if (!is_file($absolute)) {
            throw FilesystemException::missing($path);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, path guarded.
        $contents = file_get_contents($absolute);

        return $contents !== false ? $contents : throw FilesystemException::failed('read', $path);
    }

    public function write(string $path, string $contents): void
    {
        $absolute = $this->absolute($path);
        $this->ensureDirectory(dirname($absolute), $path);

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- see class docblock.
        if (file_put_contents($absolute, $contents, LOCK_EX) === false) {
            throw FilesystemException::failed('write', $path);
        }
    }

    public function exists(string $path): bool
    {
        return file_exists($this->absolute($path));
    }

    public function isFile(string $path): bool
    {
        return is_file($this->absolute($path));
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($this->absolute($path));
    }

    public function delete(string $path): void
    {
        $absolute = $this->absolute($path);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- see class docblock.
        if (is_file($absolute) && !unlink($absolute)) {
            throw FilesystemException::failed('delete', $path);
        }
    }

    public function makeDirectory(string $path): void
    {
        $this->ensureDirectory($this->absolute($path), $path);
    }

    public function deleteDirectory(string $path): void
    {
        $absolute = $this->absolute($path);
        if (!is_dir($absolute)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions -- see class docblock.
            $ok = $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$ok) {
                throw FilesystemException::failed('delete', $path);
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- see class docblock.
        if ($absolute !== $this->root && !rmdir($absolute)) {
            throw FilesystemException::failed('delete', $path);
        }
    }

    public function copy(string $from, string $to): void
    {
        $source = $this->absolute($from);
        if (!is_file($source)) {
            throw FilesystemException::missing($from);
        }

        $target = $this->absolute($to);
        $this->ensureDirectory(dirname($target), $to);
        if (!copy($source, $target)) {
            throw FilesystemException::failed('copy to', $to);
        }
    }

    public function move(string $from, string $to): void
    {
        $source = $this->absolute($from);
        if (!is_file($source)) {
            throw FilesystemException::missing($from);
        }

        $target = $this->absolute($to);
        $this->ensureDirectory(dirname($target), $to);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- see class docblock.
        if (!rename($source, $target)) {
            throw FilesystemException::failed('move to', $to);
        }
    }

    public function size(string $path): int
    {
        $absolute = $this->absolute($path);
        $size = is_file($absolute) ? filesize($absolute) : false;

        return $size !== false ? $size : throw FilesystemException::missing($path);
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $absolute = $this->absolute($directory);
        if (!is_dir($absolute)) {
            return [];
        }

        $iterator = $recursive
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS))
            : new FilesystemIterator($absolute, FilesystemIterator::SKIP_DOTS);

        $found = [];
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relative = substr(str_replace('\\', '/', $item->getPathname()), strlen($this->root) + 1);
                $found[] = $relative;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * The absolute path for a guarded relative path. If it already exists through a symlink, it
     * must still resolve inside the root.
     */
    private function absolute(string $path): string
    {
        $relative = PathGuard::normalize($path);
        $absolute = $relative === '' ? $this->root : $this->root . '/' . $relative;

        $real = realpath($absolute);
        if ($real !== false) {
            $real = str_replace('\\', '/', $real);
            if ($real !== $this->root && !str_starts_with($real, $this->root . '/')) {
                throw FilesystemException::unsafePath($path, 'resolves outside the root');
            }
        }

        return $absolute;
    }

    private function ensureDirectory(string $absolute, string $path): void
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- see class docblock.
        if (!is_dir($absolute) && !mkdir($absolute, 0755, true) && !is_dir($absolute)) {
            throw FilesystemException::failed('create directory for', $path);
        }
    }
}
