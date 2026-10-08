<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\CommandRunner;
use Codad5\WPToolkit\Build\JsonConfig;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class JsonConfigTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/wptoolkit-jsonconfig-' . uniqid() . '/my-plugin';
        mkdir($this->project . '/docs', 0777, true);
        file_put_contents($this->project . '/my-plugin.php', "<?php\n/*\n * Plugin Name: My Plugin\n * Version: 0.9.0\n */\n");
        file_put_contents($this->project . '/package.json', '{"version": "1.0.0"}');
        file_put_contents($this->project . '/docs/a.md', 'docs');
        file_put_contents($this->project . '/.distignore', "/docs\n");
    }

    protected function tearDown(): void
    {
        $root = dirname($this->project);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }

    public function test_the_config_file_drives_the_same_pipeline(): void
    {
        file_put_contents($this->project . '/wptoolkit.json', json_encode([
            'version' => 'package.json',
            'syncVersionTo' => ['header'],
            'run' => ['npm run build'],
            'exclude' => ['.distignore'],
            'zip' => 'out/{slug}-{version}.zip',
        ]));
        $ran = [];

        [$build, $zip] = JsonConfig::load($this->project);
        $report = $build->withOutput(static function () {
        })->withRunner(new class ($ran) implements CommandRunner {
            /** @param list<string> $ran */
            public function __construct(private array &$ran)
            {
            }

            public function run(string $command, string $workingDirectory): array
            {
                $this->ran[] = $command;
                return ['exitCode' => 0, 'output' => ''];
            }
        })->zip($zip);

        self::assertSame('1.0.0', $report->version);
        self::assertSame(['npm run build'], $ran);
        self::assertStringEndsWith('out/my-plugin-1.0.0.zip', str_replace('\\', '/', $report->zip));

        $archive = new ZipArchive();
        $archive->open($report->zip);
        self::assertStringContainsString('Version: 1.0.0', (string) $archive->getFromName('my-plugin/my-plugin.php'));
        self::assertFalse($archive->locateName('my-plugin/docs/a.md'), '.distignore was applied');
        $archive->close();
    }

    public function test_no_config_file_means_sensible_defaults(): void
    {
        file_put_contents($this->project . '/my-plugin.php', "<?php\n/*\n * Plugin Name: My Plugin\n * Version: 1.0.0\n */\n");

        [$build, $zip] = JsonConfig::load($this->project);

        self::assertSame('dist/{slug}-{version}.zip', $zip);
        self::assertSame('1.0.0', $build->withOutput(static function () {
        })->zip($zip)->version);
    }

    public function test_unknown_version_source_is_explained(): void
    {
        file_put_contents($this->project . '/wptoolkit.json', '{"version": "pyproject.toml"}');

        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('Unknown "version" source "pyproject.toml"');

        JsonConfig::load($this->project);
    }
}
