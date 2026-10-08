<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Filesystem;

use Codad5\WPToolkit\Exceptions\FilesystemException;

/**
 * Files under one root directory (ADR-0004; trimmed port of 0.x `Utils\Filesystem`).
 *
 * Every path is relative to the root and is checked before use: `..` segments, absolute paths,
 * null bytes and stream wrappers are rejected, so user input can't reach outside the root.
 */
interface Filesystem
{
    /**
     * @throws FilesystemException When the file is missing or unreadable.
     */
    public function read(string $path): string;

    /**
     * Create or replace a file, creating parent directories as needed.
     *
     * @throws FilesystemException
     */
    public function write(string $path, string $contents): void;

    public function exists(string $path): bool;

    public function isFile(string $path): bool;

    public function isDirectory(string $path): bool;

    /**
     * Delete a file. Deleting something that doesn't exist is not an error.
     *
     * @throws FilesystemException
     */
    public function delete(string $path): void;

    /**
     * @throws FilesystemException
     */
    public function makeDirectory(string $path): void;

    /**
     * Delete a directory and everything in it.
     *
     * @throws FilesystemException
     */
    public function deleteDirectory(string $path): void;

    /**
     * @throws FilesystemException
     */
    public function copy(string $from, string $to): void;

    /**
     * @throws FilesystemException
     */
    public function move(string $from, string $to): void;

    /**
     * @throws FilesystemException
     */
    public function size(string $path): int;

    /**
     * Files (not directories) under a directory, as paths relative to the root, sorted.
     *
     * @return list<string>
     */
    public function files(string $directory = '', bool $recursive = false): array;
}
