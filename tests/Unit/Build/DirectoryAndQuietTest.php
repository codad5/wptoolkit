<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\Steps\ScopeToolkit;
use PHPUnit\Framework\TestCase;

/**
 * toDirectory(): the same build and verification as zip(), written as a folder (rsync, SVN, a test
 * site). quiet(): no progress or summary. Writing a folder can destroy things, so most of this is
 * about what it refuses to overwrite.
 */
final class DirectoryAndQuietTest extends TestCase
{
    private string $root;

    private string $project;

    /** @var list<string> */
    private array $lines = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wptoolkit-dir-' . bin2hex(random_bytes(4));
        $this->project = $this->root . '/my-plugin';
        mkdir($this->project . '/src', 0777, true);
        mkdir($this->project . '/tests', 0777, true);
        file_put_contents($this->project . '/my-plugin.php', "<?php\n/*\n * Plugin Name: My Plugin\n * Version: 1.2.0\n */\n");
        file_put_contents($this->project . '/src/Plugin.php', '<?php // plugin');
        file_put_contents($this->project . '/tests/PluginTest.php', '<?php // test');
        file_put_contents($this->project . '/composer.json', '{}');
        $this->lines = [];
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    // --- toDirectory() -------------------------------------------------------------------------

    public function test_a_directory_build_holds_exactly_what_a_zip_would(): void
    {
        $zip = $this->build()->zip('dist/{slug}.zip');
        $report = $this->build()->toDirectory('dist/{slug}-{version}');

        $directory = $this->project . '/dist/my-plugin-1.2.0';
        self::assertSame(str_replace('\\', '/', $directory), $report->directory);
        self::assertFileExists($directory . '/my-plugin.php');
        self::assertFileExists($directory . '/src/Plugin.php');
        self::assertFileDoesNotExist($directory . '/tests/PluginTest.php', 'default excludes apply');
        self::assertFileDoesNotExist($directory . '/composer.json');
        self::assertDirectoryDoesNotExist($directory . '/dist', 'earlier output is not packaged into new output');

        self::assertSame(['my-plugin.php', 'src/Plugin.php'], $report->files);
        self::assertSame(
            array_map(static fn (string $f): string => substr($f, strlen('my-plugin/')), $zip->files),
            $report->files,
            'the same files as the zip, without its top-level folder'
        );
        self::assertSame('', $report->zip);
        self::assertSame('', $report->sha256);
        self::assertStringContainsString('my-plugin-1.2.0/', $report->summary());
        self::assertStringNotContainsString('sha256', $report->summary());
    }

    public function test_commands_and_verification_run_as_they_do_for_a_zip(): void
    {
        file_put_contents($this->project . '/my-plugin.php', "<?php\n/*\n * Plugin Name: My Plugin\n * Version: 9.9.9\n */\n");
        file_put_contents($this->project . '/package.json', '{"version": "1.2.0"}');

        try {
            $this->build()->version(\Codad5\WPToolkit\Build\Version::fromPackageJson())->toDirectory('out');
            self::fail('A stale Version header must fail verification.');
        } catch (BuildException $e) {
            self::assertStringContainsString('Version header', $e->getMessage());
        }
        self::assertDirectoryDoesNotExist($this->project . '/out', 'nothing is written when verification fails');
    }

    public function test_a_previous_build_of_the_same_project_is_replaced_not_merged(): void
    {
        $this->build()->toDirectory('out');
        file_put_contents($this->project . '/out/stale-from-last-build.php', '<?php');
        unlink($this->project . '/src/Plugin.php');

        $this->build()->toDirectory('out');

        self::assertFileDoesNotExist($this->project . '/out/stale-from-last-build.php');
        self::assertFileDoesNotExist($this->project . '/out/src/Plugin.php');
        self::assertFileExists($this->project . '/out/my-plugin.php');
    }

    public function test_an_empty_existing_directory_is_fine(): void
    {
        mkdir($this->root . '/empty');

        $this->build()->toDirectory($this->root . '/empty');

        self::assertFileExists($this->root . '/empty/my-plugin.php');
    }

    public function test_a_directory_holding_something_else_is_never_overwritten(): void
    {
        mkdir($this->root . '/photos');
        file_put_contents($this->root . '/photos/holiday.jpg', 'precious');

        try {
            $this->build()->toDirectory($this->root . '/photos');
            self::fail('Expected a refusal.');
        } catch (BuildException $e) {
            self::assertStringContainsString('not empty', $e->getMessage());
        }
        self::assertSame('precious', file_get_contents($this->root . '/photos/holiday.jpg'));
        self::assertFileDoesNotExist($this->root . '/photos/my-plugin.php');
    }

    /**
     * @dataProvider dangerousTargets
     */
    public function test_the_project_itself_and_its_parents_cannot_be_the_target(string $target): void
    {
        $before = file_get_contents($this->project . '/src/Plugin.php');

        try {
            $this->build()->toDirectory($target === 'PARENT' ? $this->root : $target);
            self::fail('Expected a refusal.');
        } catch (BuildException $e) {
            self::assertStringContainsString('project', $e->getMessage());
        }
        self::assertSame($before, file_get_contents($this->project . '/src/Plugin.php'));
        self::assertFileExists($this->project . '/tests/PluginTest.php');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dangerousTargets(): array
    {
        return [
            'the project' => ['.'],
            'the project, spelled another way' => ['dist/..'],
            'the folder above, by ..' => ['..'],
            'the folder above the project' => ['PARENT'],
        ];
    }

    public function test_the_project_path_in_another_letter_case_is_still_the_project(): void
    {
        // One folder on Windows and macOS; the project's own main file would otherwise make it look
        // like an earlier build, and it would be emptied.
        try {
            $this->build()->toDirectory(strtoupper($this->project));
            self::fail('Expected a refusal.');
        } catch (BuildException $e) {
            self::assertStringContainsString('project', $e->getMessage());
        }
        self::assertFileExists($this->project . '/src/Plugin.php');
        self::assertFileExists($this->project . '/tests/PluginTest.php');
    }

    public function test_a_source_folder_of_the_project_is_never_overwritten(): void
    {
        try {
            $this->build()->toDirectory('src');
            self::fail('Expected a refusal.');
        } catch (BuildException $e) {
            self::assertStringContainsString('not empty', $e->getMessage());
        }
        self::assertSame('<?php // plugin', file_get_contents($this->project . '/src/Plugin.php'));
    }

    public function test_the_working_tree_is_not_modified(): void
    {
        $before = $this->snapshot($this->project);

        $this->build()->toDirectory($this->root . '/elsewhere');

        self::assertSame($before, $this->snapshot($this->project));
    }

    public function test_a_built_directory_can_be_scoped_and_checked_like_a_zip(): void
    {
        // The step list is shared: scope() without a bundled toolkit fails the same way for both.
        $this->expectException(BuildException::class);
        $this->expectExceptionMessage('No bundled WPToolkit found');
        $this->build()->step(new ScopeToolkit('Acme\\WPToolkit'))->toDirectory('out');
    }

    // --- quiet() -------------------------------------------------------------------------------

    public function test_quiet_prints_neither_progress_nor_the_summary_but_still_returns_the_report(): void
    {
        $report = $this->build()->quiet()->zip('dist/{slug}.zip');

        self::assertSame([], $this->lines);
        self::assertCount(2, $report->files);
        self::assertNotSame('', $report->sha256);
    }

    public function test_quiet_still_prints_warnings(): void
    {
        $warns = new class implements \Codad5\WPToolkit\Build\BuildStep {
            public function name(): string
            {
                return 'warns';
            }

            public function phase(): \Codad5\WPToolkit\Build\Phase
            {
                return \Codad5\WPToolkit\Build\Phase::Transform;
            }

            public function run(\Codad5\WPToolkit\Build\BuildContext $context): void
            {
                $context->say('routine progress');
                $context->warn('something a person should read');
            }
        };

        $report = $this->build()->quiet()->step($warns)->zip('dist/{slug}.zip');

        self::assertCount(1, $this->lines);
        self::assertStringContainsString('something a person should read', $this->lines[0]);
        self::assertSame(['something a person should read'], $report->warnings);
    }

    public function test_quiet_wins_over_verbose(): void
    {
        $this->build()->quiet()->verbose()->run(escapeshellarg(PHP_BINARY) . ' -v')->zip('dist/{slug}.zip');

        self::assertSame([], $this->lines);
    }

    private function build(): Build
    {
        return Build::plugin($this->project)->withOutput(function (string $line): void {
            $this->lines[] = $line;
        });
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[str_replace('\\', '/', substr($file->getPathname(), strlen($directory)))] = (string) md5_file($file->getPathname());
        }
        ksort($files);

        return $files;
    }
}
