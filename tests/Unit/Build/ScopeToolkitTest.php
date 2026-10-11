<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\ProcessCommandRunner;
use Codad5\WPToolkit\Build\Project;
use Codad5\WPToolkit\Build\Steps\ScopeToolkit;
use PHPUnit\Framework\TestCase;

/**
 * scope() must leave a plugin that loads: the bundled toolkit, the plugin's own code and Composer's
 * autoload map all have to agree on the namespace. These run the real bin/scope.php and the real
 * `composer dump-autoload`, then load the result in a separate PHP process.
 */
final class ScopeToolkitTest extends TestCase
{
    private const TARGET = 'Acme\\Forms\\Lib\\WPToolkit';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wptk-scope-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/src', 0777, true);
        $this->file('acme-forms.php', "<?php\n/**\n * Plugin Name: Acme Forms\n * Version: 1.0.0\n */\n");
        $this->file('src/FormsProvider.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Acme\Forms;

            use Codad5\WPToolkit\Foundation\ServiceProvider;
            use Codad5\WPToolkit\Http\Router;

            final class FormsProvider extends ServiceProvider
            {
                public function boot(Router $router): void
                {
                    $name = \Codad5\WPToolkit\Foundation\Application::class;
                }
            }
            PHP);
        // A build script names the packager, which scope.php never renames: it must be left alone.
        $this->file('build.php', "<?php\nuse Codad5\\WPToolkit\\Build\\Build;\n");
    }

    protected function tearDown(): void
    {
        $this->remove($this->dir);
    }

    public function test_a_composer_installed_plugin_loads_after_scoping(): void
    {
        $this->requireComposer();
        $this->installToolkitAsComposerWould();

        $this->scope();

        $provider = (string) file_get_contents($this->dir . '/src/FormsProvider.php');
        self::assertStringContainsString('use Acme\Forms\Lib\WPToolkit\Foundation\ServiceProvider;', $provider);
        self::assertStringContainsString('\Acme\Forms\Lib\WPToolkit\Foundation\Application::class', $provider);
        self::assertStringNotContainsString('Codad5\WPToolkit', $provider);

        self::assertSame('ok', $this->php(<<<'PHP'
            require 'vendor/autoload.php';
            $parent = get_parent_class(Acme\Forms\FormsProvider::class);
            echo $parent === 'Acme\Forms\Lib\WPToolkit\Foundation\ServiceProvider'
                && class_exists('Acme\Forms\Lib\WPToolkit\Foundation\Application')
                && !class_exists('Codad5\WPToolkit\Foundation\Application') ? 'ok' : 'broken: ' . var_export($parent, true);
            PHP));
    }

    public function test_a_standalone_copy_and_the_plugins_code_are_scoped_together(): void
    {
        $this->copyToolkitTo('lib/wptoolkit');

        $this->scope();

        self::assertStringContainsString('use Acme\Forms\Lib\WPToolkit\Http\Router;', (string) file_get_contents($this->dir . '/src/FormsProvider.php'));
        self::assertStringContainsString(
            'namespace Acme\Forms\Lib\WPToolkit\Foundation;',
            (string) file_get_contents($this->dir . '/lib/wptoolkit/src/Foundation/Application.php')
        );
    }

    public function test_the_packager_namespace_and_other_vendor_packages_are_left_alone(): void
    {
        $this->copyToolkitTo('lib/wptoolkit');
        $this->file('vendor/other/pkg/src/Thing.php', "<?php\nuse Codad5\\WPToolkit\\Http\\Router;\n");

        $this->scope();

        self::assertStringContainsString('use Codad5\WPToolkit\Build\Build;', (string) file_get_contents($this->dir . '/build.php'));
        self::assertStringContainsString('Codad5\WPToolkit\Http\Router', (string) file_get_contents($this->dir . '/vendor/other/pkg/src/Thing.php'));
    }

    public function test_a_broken_result_fails_the_build_instead_of_shipping(): void
    {
        $this->requireComposer();
        $this->installToolkitAsComposerWould();
        // Composer metadata that no longer describes the package: the autoload map can't be fixed.
        file_put_contents($this->dir . '/vendor/composer/installed.json', '{"packages": [], "dev": false, "dev-package-names": []}');

        $this->expectException(BuildException::class);
        $this->scope();
    }

    public function test_a_class_named_in_a_string_is_reported_because_strings_are_not_scoped(): void
    {
        $this->copyToolkitTo('lib/wptoolkit');
        $this->file('src/Legacy.php', "<?php\n\$class = 'Codad5\\\\WPToolkit\\\\Http\\\\Router';\n");

        $context = $this->scope();

        self::assertCount(1, $context->warnings);
        self::assertStringContainsString('src/Legacy.php', $context->warnings[0]);
    }

    public function test_group_use_statements_and_the_main_file_are_scoped(): void
    {
        $this->copyToolkitTo('lib/wptoolkit');
        $this->file('src/Grouped.php', "<?php\nnamespace Acme\\Forms;\nuse Codad5\\WPToolkit\\{Http\\Router, Foundation\\Identity};\n");
        file_put_contents($this->dir . '/acme-forms.php', "\n\\Codad5\\WPToolkit\\Foundation\\Application::create(__FILE__, array());\n", FILE_APPEND);

        $this->scope();

        self::assertStringContainsString('use Acme\Forms\Lib\WPToolkit\{Http\Router, Foundation\Identity};', (string) file_get_contents($this->dir . '/src/Grouped.php'));
        self::assertStringContainsString('\Acme\Forms\Lib\WPToolkit\Foundation\Application::create', (string) file_get_contents($this->dir . '/acme-forms.php'));
    }

    public function test_verify_refuses_a_scoped_library_with_unscoped_callers(): void
    {
        // The library was scoped by hand (bin/scope.php) but the plugin's code was not: a fatal error on load.
        $this->copyToolkitTo('lib/wptoolkit');
        (new ProcessCommandRunner())->run(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->dir . '/lib/wptoolkit/bin/scope.php') . ' ' . escapeshellarg(self::TARGET),
            $this->dir
        );
        unlink($this->dir . '/build.php');

        try {
            Build::plugin($this->dir)->withOutput(static function (): void {
            })->zip($this->dir . '-out.zip');
            self::fail('Expected the build to be refused.');
        } catch (BuildException $e) {
            self::assertStringContainsString('src/FormsProvider.php', $e->getMessage());
            self::assertFileDoesNotExist($this->dir . '-out.zip');
        }
    }

    public function test_composer_after_scope_is_refused_because_it_would_undo_the_scoping(): void
    {
        $this->expectException(BuildException::class);
        Build::plugin($this->dir)->scope(self::TARGET)->composer();
    }

    private function scope(): BuildContext
    {
        $context = new BuildContext(Project::plugin($this->dir), $this->dir, '1.0.0', new ProcessCommandRunner(), static function (): void {
        });
        (new ScopeToolkit(self::TARGET))->run($context);

        return $context;
    }

    private function installToolkitAsComposerWould(): void
    {
        $this->copyToolkitTo('vendor/codad5/wptoolkit');
        $this->file('composer.json', (string) json_encode([
            'name' => 'acme/forms',
            'require' => ['codad5/wptoolkit' => '^1.0'],
            'autoload' => ['psr-4' => ['Acme\\Forms\\' => 'src/']],
        ]));
        $this->file('vendor/composer/installed.json', (string) json_encode([
            'packages' => [[
                'name' => 'codad5/wptoolkit',
                'version' => '1.0.0',
                'version_normalized' => '1.0.0.0',
                'type' => 'library',
                'install-path' => '../codad5/wptoolkit',
                'autoload' => ['psr-4' => ['Codad5\\WPToolkit\\' => 'src/', 'Codad5\\WPToolkit\\Build\\' => 'packages/build/src/']],
            ]],
            'dev' => false,
            'dev-package-names' => [],
        ]));
    }

    private function copyToolkitTo(string $relative): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['src', 'bootstrap', 'bin/scope.php', 'composer.json'] as $item) {
            $this->copy($root . '/' . $item, $this->dir . '/' . $relative . '/' . $item);
        }
    }

    private function php(string $code): string
    {
        $script = $this->dir . '/check.php';
        file_put_contents($script, "<?php\n" . $code);
        $result = (new ProcessCommandRunner())->run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $this->dir);
        unlink($script);

        return trim($result['output']);
    }

    private function requireComposer(): void
    {
        if ((new ProcessCommandRunner())->run('composer --version', $this->dir)['exitCode'] !== 0) {
            self::markTestSkipped('Composer is not on the PATH (https://github.com/codad5/wptoolkit/issues — required for this test).');
        }
    }

    private function file(string $relative, string $contents): void
    {
        $path = $this->dir . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
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

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
