<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;

/**
 * `wp {slug} make:*` inside a scoped copy must write files that load: the stubs name the library's
 * original namespace, which does not exist in a plugin that bundles a scoped copy. This scopes a
 * real copy with bin/scope.php and scaffolds from it in a separate PHP process.
 */
final class ScopedScaffolderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wptk-scaffold-' . bin2hex(random_bytes(4));
        $root = dirname(__DIR__, 3);
        foreach (['src', 'bootstrap', 'resources/stubs', 'bin/scope.php'] as $item) {
            $this->copy($root . '/' . $item, $this->dir . '/lib/' . $item);
        }
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->dir . '/lib/bin/scope.php') . ' ' . escapeshellarg('Acme\\Lib\\WPToolkit') . ' 2>&1', $output, $code);
        self::assertSame(0, $code, implode("\n", $output));
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->dir);
    }

    public function test_files_scaffolded_from_a_scoped_copy_use_its_namespace_and_load(): void
    {
        $script = $this->dir . '/run.php';
        file_put_contents($script, <<<'PHP'
            <?php
            spl_autoload_register(static function (string $class): void {
                foreach (['Acme\\Lib\\WPToolkit\\' => __DIR__ . '/lib/src/', 'MyPlugin\\' => __DIR__ . '/plugin/src/'] as $prefix => $dir) {
                    if (str_starts_with($class, $prefix) && is_file($file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php')) {
                        require $file;
                    }
                }
            });
            $scaffolder = new Acme\Lib\WPToolkit\Cli\Scaffolder(
                __DIR__ . '/plugin',
                'MyPlugin',
                'my-plugin',
                'my-plugin',
                __DIR__ . '/lib/resources/stubs',
                new Acme\Lib\WPToolkit\Adapters\Clock\SystemClock()
            );
            foreach (['entity' => 'Book', 'controller' => 'Book', 'provider' => 'Admin', 'field' => 'Stars', 'migration' => 'Rename', 'shortcode' => 'Widget'] as $kind => $name) {
                $path = $scaffolder->make($kind, $name);
                if (str_contains(file_get_contents($path), 'Codad5\\WPToolkit')) {
                    exit("{$kind}: still names the unscoped library");
                }
            }
            // Loading these resolves their parent classes through the scoped copy.
            echo get_parent_class(MyPlugin\AdminServiceProvider::class), '|', get_parent_class(MyPlugin\Entities\Book::class);
            PHP);

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $output, $code);

        self::assertSame(
            'Acme\Lib\WPToolkit\Foundation\ServiceProvider|Acme\Lib\WPToolkit\Data\Entity',
            implode("\n", $output)
        );
        self::assertSame(0, $code);
    }

    private function copy(string $from, string $to): void
    {
        if (is_file($from)) {
            if (!is_dir(dirname($to))) {
                mkdir(dirname($to), 0777, true);
            }
            copy($from, $to);
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $this->copy($file->getPathname(), $to . substr($file->getPathname(), strlen($from)));
        }
    }
}
