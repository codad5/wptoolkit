<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Filesystem;

use Codad5\WPToolkit\Contracts\Filesystem\Filesystem;
use Codad5\WPToolkit\Exceptions\FilesystemException;

/**
 * A filesystem in memory: for tests, and for staging content before writing it out.
 */
final class InMemoryFilesystem implements Filesystem
{
    /** @var array<string, string> path => contents */
    private array $files = [];

    /** @var array<string, true> */
    private array $directories = [];

    public function read(string $path): string
    {
        $path = PathGuard::normalize($path);

        return $this->files[$path] ?? throw FilesystemException::missing($path);
    }

    public function write(string $path, string $contents): void
    {
        $path = PathGuard::normalize($path);
        if ($path === '' || isset($this->directories[$path])) {
            throw FilesystemException::failed('write', $path);
        }

        $this->makeParents($path);
        $this->files[$path] = $contents;
    }

    public function exists(string $path): bool
    {
        $path = PathGuard::normalize($path);

        return $path === '' || isset($this->files[$path]) || isset($this->directories[$path]);
    }

    public function isFile(string $path): bool
    {
        return isset($this->files[PathGuard::normalize($path)]);
    }

    public function isDirectory(string $path): bool
    {
        $path = PathGuard::normalize($path);

        return $path === '' || isset($this->directories[$path]);
    }

    public function delete(string $path): void
    {
        unset($this->files[PathGuard::normalize($path)]);
    }

    public function makeDirectory(string $path): void
    {
        $path = PathGuard::normalize($path);
        if (isset($this->files[$path])) {
            throw FilesystemException::failed('create directory', $path);
        }
        if ($path !== '') {
            $this->makeParents($path . '/x');
        }
    }

    public function deleteDirectory(string $path): void
    {
        $path = PathGuard::normalize($path);
        $prefix = $path === '' ? '' : $path . '/';

        foreach (array_keys($this->files) as $file) {
            if ($prefix === '' || str_starts_with($file, $prefix)) {
                unset($this->files[$file]);
            }
        }
        foreach (array_keys($this->directories) as $directory) {
            if ($directory === $path || $prefix === '' || str_starts_with($directory, $prefix)) {
                unset($this->directories[$directory]);
            }
        }
    }

    public function copy(string $from, string $to): void
    {
        $this->write($to, $this->read($from));
    }

    public function move(string $from, string $to): void
    {
        $this->copy($from, $to);
        $this->delete($from);
    }

    public function size(string $path): int
    {
        return strlen($this->read($path));
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        $directory = PathGuard::normalize($directory);
        $prefix = $directory === '' ? '' : $directory . '/';
        $found = [];

        foreach (array_keys($this->files) as $file) {
            if ($prefix !== '' && !str_starts_with($file, $prefix)) {
                continue;
            }
            if (!$recursive && str_contains(substr($file, strlen($prefix)), '/')) {
                continue;
            }
            $found[] = $file;
        }

        sort($found);

        return $found;
    }

    private function makeParents(string $path): void
    {
        $parent = dirname($path);
        while ($parent !== '.' && $parent !== '') {
            $this->directories[$parent] = true;
            $parent = dirname($parent);
        }
    }
}
