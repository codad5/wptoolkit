<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Contract;

use Codad5\WPToolkit\Contracts\Filesystem\Filesystem;
use Codad5\WPToolkit\Exceptions\FilesystemException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The behaviour every Filesystem adapter must have.
 */
abstract class FilesystemContract extends TestCase
{
    abstract protected function filesystem(): Filesystem;

    public function test_write_then_read_creating_parent_directories(): void
    {
        $fs = $this->filesystem();

        $fs->write('exports/2026/report.csv', "a,b\n");

        self::assertSame("a,b\n", $fs->read('exports/2026/report.csv'));
        self::assertTrue($fs->isFile('exports/2026/report.csv'));
        self::assertTrue($fs->isDirectory('exports/2026'));
        self::assertSame(4, $fs->size('exports/2026/report.csv'));
    }

    public function test_reading_a_missing_file_throws(): void
    {
        $this->expectException(FilesystemException::class);

        $this->filesystem()->read('nope.txt');
    }

    public function test_delete_is_idempotent(): void
    {
        $fs = $this->filesystem();
        $fs->write('a.txt', 'x');

        $fs->delete('a.txt');
        $fs->delete('a.txt');

        self::assertFalse($fs->exists('a.txt'));
    }

    public function test_copy_and_move(): void
    {
        $fs = $this->filesystem();
        $fs->write('a.txt', 'x');

        $fs->copy('a.txt', 'copies/b.txt');
        $fs->move('a.txt', 'moved/c.txt');

        self::assertSame('x', $fs->read('copies/b.txt'));
        self::assertSame('x', $fs->read('moved/c.txt'));
        self::assertFalse($fs->exists('a.txt'));
    }

    public function test_files_lists_relative_paths_sorted(): void
    {
        $fs = $this->filesystem();
        $fs->write('d/b.txt', '1');
        $fs->write('d/a.txt', '2');
        $fs->write('d/sub/c.txt', '3');

        self::assertSame(['d/a.txt', 'd/b.txt'], $fs->files('d'));
        self::assertSame(['d/a.txt', 'd/b.txt', 'd/sub/c.txt'], $fs->files('d', recursive: true));
    }

    public function test_delete_directory_removes_everything_inside(): void
    {
        $fs = $this->filesystem();
        $fs->write('tmp/x/y.txt', '1');

        $fs->deleteDirectory('tmp');

        self::assertFalse($fs->exists('tmp/x/y.txt'));
        self::assertFalse($fs->isDirectory('tmp'));
    }

    public function test_dot_segments_inside_the_root_are_fine(): void
    {
        $fs = $this->filesystem();
        $fs->write('a/./b/../c.txt', 'ok');

        self::assertSame('ok', $fs->read('a/c.txt'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafePaths(): iterable
    {
        yield 'parent traversal' => ['../outside.txt'];
        yield 'nested traversal' => ['a/../../outside.txt'];
        yield 'windows traversal' => ['a\\..\\..\\outside.txt'];
        yield 'absolute unix' => ['/etc/passwd'];
        yield 'absolute windows' => ['C:\\Windows\\win.ini'];
        yield 'stream wrapper' => ['phar://archive.phar/x'];
        yield 'php wrapper' => ['php://filter/resource=x'];
        yield 'null byte' => ["a.txt\0.jpg"];
    }

    #[DataProvider('unsafePaths')]
    public function test_unsafe_paths_are_refused_for_reading(string $path): void
    {
        $this->expectException(FilesystemException::class);

        $this->filesystem()->read($path);
    }

    #[DataProvider('unsafePaths')]
    public function test_unsafe_paths_are_refused_for_writing(string $path): void
    {
        $this->expectException(FilesystemException::class);

        $this->filesystem()->write($path, 'pwned');
    }
}
