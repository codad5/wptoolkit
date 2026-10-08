<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Filesystem;

use Codad5\WPToolkit\Adapters\Filesystem\LocalFilesystem;
use Codad5\WPToolkit\Contracts\Filesystem\Filesystem;
use Codad5\WPToolkit\Exceptions\FilesystemException;
use Codad5\WPToolkit\Tests\Contract\FilesystemContract;

/**
 * Runs against a real temporary directory.
 */
final class LocalFilesystemTest extends FilesystemContract
{
    private string $base;

    private ?LocalFilesystem $fs = null;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/wptoolkit-fs-' . uniqid();
        mkdir($this->base . '/root', 0777, true);
        file_put_contents($this->base . '/outside.txt', 'secret');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->base);
    }

    protected function filesystem(): Filesystem
    {
        return $this->fs ??= new LocalFilesystem($this->base . '/root');
    }

    public function test_nothing_outside_the_root_was_touched(): void
    {
        foreach (self::unsafePaths() as [$path]) {
            try {
                $this->filesystem()->write($path, 'pwned');
            } catch (FilesystemException) {
            }
        }

        self::assertSame('secret', file_get_contents($this->base . '/outside.txt'));
    }

    public function test_a_missing_root_is_refused(): void
    {
        $this->expectException(FilesystemException::class);

        new LocalFilesystem($this->base . '/does-not-exist');
    }
}
