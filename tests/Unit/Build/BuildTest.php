<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\CommandRunner;
use Codad5\WPToolkit\Build\Patterns;
use Codad5\WPToolkit\Build\Phase;
use Codad5\WPToolkit\Build\Version;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Track P: the fluent build library, on a project shaped like the real ones whose zips shipped
 * PHPUnit, .claude/ and node_modules.
 */
final class BuildTest extends TestCase
{
    private string $tmp;

    private string $project;

    /** @var list<array{string, string}> */
    private array $commands = [];

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/wptoolkit-buildtest-' . uniqid();
        $this->project = $this->tmp . '/acme-shop';

        $this->file('acme-shop.php', "<?php\n/**\n * Plugin Name: Acme Shop\n * Version: 1.0.0\n * Text Domain: acme-shop\n */\ndefine('ACME_SHOP_VERSION', '1.0.0');\n");
        $this->file('readme.txt', "=== Acme Shop ===\nStable tag: 1.0.0\n");
        $this->file('package.json', '{"name": "acme-shop", "version": "1.2.3"}');
        $this->file('composer.json', '{"name": "acme/shop"}');
        $this->file('README.md', '# dev readme');
        $this->file('src/Shop.php', '<?php // shop');
        $this->file('assets/dist/app.js', 'console.log(1)');
        $this->file('vendor/autoload.php', '<?php // autoload');
        $this->file('vendor/acme/money/Money.php', '<?php // runtime dependency');
        $this->file('vendor/phpunit/phpunit/PHPUnit.php', '<?php // dev dependency');
        $this->file('node_modules/left-pad/index.js', '');
        $this->file('tests/ShopTest.php', '<?php');
        $this->file('.git/config', '[core]');
        $this->file('.claude/skills/x.md', 'agent config');
        $this->file('.gitignore', "/assets/dist/\n/vendor/\n");
        $this->file('.distignore', "/docs\n");
        $this->file('docs/guide.md', 'docs');
    }

    protected function tearDown(): void
    {
        $this->remove($this->tmp);
    }

    public function test_a_full_build_ships_only_what_belongs_in_the_plugin(): void
    {
        $report = $this->build()
            ->version(Version::fromPackageJson())
            ->syncVersionTo('header', 'readme.txt', 'ACME_SHOP_VERSION')
            ->run('npm run build')
            ->composer(noDev: true)
            ->exclude(Patterns::fromDistignore())
            ->zip();

        self::assertSame('1.2.3', $report->version);
        self::assertSame(
            str_replace('\\', '/', (string) realpath($this->project)) . '/dist/acme-shop-1.2.3.zip',
            str_replace('\\', '/', $report->zip)
        );
        self::assertFileExists($report->zip . '.sha256');

        $files = $this->zipFiles($report->zip);
        foreach (['acme-shop/acme-shop.php', 'acme-shop/readme.txt', 'acme-shop/src/Shop.php', 'acme-shop/assets/dist/app.js', 'acme-shop/vendor/autoload.php', 'acme-shop/vendor/acme/money/Money.php'] as $shipped) {
            self::assertContains($shipped, $files);
        }
        foreach (['vendor/phpunit', 'node_modules', 'tests/', '.git/', '.claude', 'README.md', 'package.json', 'composer.json', 'docs/', '.gitignore', '.distignore'] as $leaked) {
            self::assertEmpty(array_filter($files, static fn ($f) => str_contains($f, $leaked)), "{$leaked} must not ship");
        }

        $main = $this->zipRead($report->zip, 'acme-shop/acme-shop.php');
        self::assertStringContainsString('Version: 1.2.3', $main);
        self::assertStringContainsString("define('ACME_SHOP_VERSION', '1.2.3')", $main);
        self::assertStringContainsString('Stable tag: 1.2.3', $this->zipRead($report->zip, 'acme-shop/readme.txt'));

        self::assertSame('npm run build', $this->commands[0][0]);
        self::assertSame(str_replace('\\', '/', (string) realpath($this->project)), $this->commands[0][1], 'run() executes in the project');
        self::assertStringContainsString('composer install', $this->commands[1][0]);
        self::assertStringContainsString('--no-dev', $this->commands[1][0]);
        self::assertNotSame($this->project, $this->commands[1][1], 'composer() executes in staging, not your project');
    }

    public function test_the_working_tree_is_never_modified(): void
    {
        $before = file_get_contents($this->project . '/acme-shop.php');

        $this->build()->version(Version::fromPackageJson())->syncVersionTo('header')->composer()->zip();

        self::assertSame($before, file_get_contents($this->project . '/acme-shop.php'));
        self::assertFileExists($this->project . '/vendor/phpunit/phpunit/PHPUnit.php');
    }

    public function test_dev_dependencies_in_vendor_fail_verification(): void
    {
        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('vendor/phpunit/phpunit/PHPUnit.php');

        // No composer(): the local vendor/ (with PHPUnit) would be shipped as-is — refused.
        $this->build()->zip();
    }

    public function test_a_stale_version_header_fails_verification(): void
    {
        $this->remove($this->project . '/vendor/phpunit');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage("The Version header says 1.0.0 but the build version is 1.2.3");

        $this->build()->version(Version::fromPackageJson())->zip();
    }

    public function test_gitignore_can_be_used_with_include_to_keep_build_output(): void
    {
        $this->remove($this->project . '/vendor/phpunit');

        $report = $this->build()
            ->exclude(Patterns::fromGitignore())
            ->include('assets/dist')
            ->zip('out/{slug}.zip');

        $files = $this->zipFiles($report->zip);
        self::assertContains('acme-shop/assets/dist/app.js', $files, 'include() re-adds what .gitignore drops');
        self::assertEmpty(array_filter($files, static fn ($f) => str_contains($f, '/vendor/')), '.gitignore excluded vendor/');
    }

    public function test_custom_steps_run_in_their_phase_with_the_context(): void
    {
        $this->remove($this->project . '/vendor/phpunit');
        $step = new class implements BuildStep {
            public ?string $seenVersion = null;

            public function name(): string
            {
                return 'stamp';
            }

            public function phase(): Phase
            {
                return Phase::Transform;
            }

            public function run(BuildContext $context): void
            {
                $this->seenVersion = $context->version;
                file_put_contents($context->staged('BUILD'), 'built ' . $context->version);
            }
        };

        $report = $this->build()->step($step)->zip();

        self::assertSame('1.0.0', $step->seenVersion);
        self::assertSame('built 1.0.0', $this->zipRead($report->zip, 'acme-shop/BUILD'));
    }

    public function test_a_failing_command_names_the_step_and_shows_its_output(): void
    {
        $failing = new class implements CommandRunner {
            public function run(string $command, string $workingDirectory): array
            {
                return ['exitCode' => 2, 'output' => 'npm ERR! missing script: build'];
            }
        };

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('[run] "npm run build" exited with 2');

        Build::plugin($this->project)->withRunner($failing)->withOutput(static function () {
        })->run('npm run build')->zip();
    }

    public function test_builds_are_reproducible(): void
    {
        $this->remove($this->project . '/vendor/phpunit');

        $first = $this->build()->zip('a/{slug}.zip');
        touch($this->project . '/src/Shop.php', time() + 3600); // a different mtime must not matter
        $second = $this->build()->zip('b/{slug}.zip');

        self::assertSame($first->sha256, $second->sha256);
    }

    public function test_themes_are_detected_from_style_css(): void
    {
        $theme = $this->tmp . '/my-theme';
        mkdir($theme);
        file_put_contents($theme . '/style.css', "/*\nTheme Name: My Theme\nVersion: 2.0.0\n*/");
        file_put_contents($theme . '/functions.php', '<?php');

        $report = Build::theme($theme)->withOutput(static function () {
        })->zip();

        self::assertSame('2.0.0', $report->version);
        self::assertContains('my-theme/style.css', $this->zipFiles($report->zip));
    }

    private function build(): Build
    {
        $runner = new class ($this->commands) implements CommandRunner {
            /** @param list<array{string, string}> $commands */
            public function __construct(private array &$commands)
            {
            }

            public function run(string $command, string $workingDirectory): array
            {
                $this->commands[] = [$command, str_replace('\\', '/', $workingDirectory)];
                // Simulate `composer install --no-dev`: dev packages disappear from vendor/.
                if (str_contains($command, 'composer install') && str_contains($command, '--no-dev')) {
                    @mkdir($workingDirectory . '/vendor/acme/money', 0777, true);
                    file_put_contents($workingDirectory . '/vendor/autoload.php', '<?php // autoload');
                    file_put_contents($workingDirectory . '/vendor/acme/money/Money.php', '<?php // runtime dependency');
                }

                return ['exitCode' => 0, 'output' => ''];
            }
        };

        return Build::plugin($this->project)->withRunner($runner)->withOutput(static function () {
        });
    }

    /**
     * @return list<string>
     */
    private function zipFiles(string $zip): array
    {
        $archive = new ZipArchive();
        $archive->open($zip);
        $names = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $names[] = (string) $archive->getNameIndex($i);
        }
        $archive->close();

        return $names;
    }

    private function zipRead(string $zip, string $name): string
    {
        $archive = new ZipArchive();
        $archive->open($zip);
        $contents = (string) $archive->getFromName($name);
        $archive->close();

        return $contents;
    }

    private function file(string $relative, string $contents): void
    {
        $path = $this->project . '/' . $relative;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);
    }

    private function remove(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
