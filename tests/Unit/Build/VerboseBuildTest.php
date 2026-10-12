<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\CommandRunner;
use Codad5\WPToolkit\Build\JsonConfig;
use Codad5\WPToolkit\Build\ProcessCommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Command output is hidden unless a command fails, which makes a long `npm run build` look hung.
 * verbose() streams it as it is produced. These run real child processes.
 */
final class VerboseBuildTest extends TestCase
{
    private string $project;

    /** @var list<string> */
    private array $lines = [];

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/wptoolkit-verbose-' . bin2hex(random_bytes(4)) . '/my-plugin';
        mkdir($this->project, 0777, true);
        file_put_contents($this->project . '/my-plugin.php', "<?php\n/*\n * Plugin Name: My Plugin\n * Version: 1.0.0\n */\n");
        $this->lines = [];
    }

    protected function tearDown(): void
    {
        $root = dirname($this->project);
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }

    public function test_command_output_is_hidden_by_default(): void
    {
        $this->build()->run($this->script('quiet', 'echo "from-the-command\n";'))->zip();

        self::assertStringNotContainsString('from-the-command', implode("\n", $this->lines));
    }

    public function test_verbose_streams_every_commands_output_under_the_command(): void
    {
        $this->build()->verbose()
            ->run($this->script('one', 'echo "alpha\nbeta\n";'), $this->script('two', 'fwrite(STDERR, "to-stderr\n");'))
            ->zip();

        $output = implode("\n", $this->lines);
        self::assertStringContainsString("    | alpha\n    | beta", $output);
        self::assertStringContainsString('    | to-stderr', $output, 'stderr is streamed too');
        self::assertLessThan(strpos($output, '| alpha'), strpos($output, 'one.php'), 'the command line is printed before its output');
    }

    public function test_run_verbose_streams_only_that_command(): void
    {
        $this->build()
            ->run($this->script('hidden', 'echo "hidden-output\n";'))
            ->runVerbose($this->script('shown', 'echo "shown-output\n";'))
            ->zip();

        $output = implode("\n", $this->lines);
        self::assertStringContainsString('| shown-output', $output);
        self::assertStringNotContainsString('hidden-output', $output);
    }

    public function test_output_arrives_line_by_line_and_is_still_returned_whole(): void
    {
        $seen = [];
        $result = (new ProcessCommandRunner())->stream(
            $this->script('lines', 'echo "one\n"; echo "two\n"; echo "three";'),
            $this->project,
            static function (string $line) use (&$seen): void {
                $seen[] = $line;
            }
        );

        self::assertSame(['one', 'two', 'three'], $seen);
        self::assertSame(0, $result['exitCode']);
        self::assertSame("one\ntwo\nthree", str_replace("\r\n", "\n", $result['output']));
    }

    public function test_a_failing_streamed_command_repeats_only_the_end_of_its_output(): void
    {
        $noisy = $this->script('noisy', 'for ($i = 1; $i <= 60; $i++) { printf("line-%02d\n", $i); } exit(3);');

        try {
            $this->build()->verbose()->run($noisy)->zip();
            self::fail('Expected the build to fail.');
        } catch (BuildException $e) {
            self::assertStringContainsString('exited with 3', $e->getMessage());
            self::assertStringContainsString('line-60', $e->getMessage());
            self::assertStringNotContainsString('line-01', $e->getMessage(), 'already printed once while streaming');
        }
        self::assertStringContainsString('| line-01', implode("\n", $this->lines));
    }

    public function test_a_failing_quiet_command_still_shows_all_of_its_output(): void
    {
        $noisy = $this->script('noisy', 'for ($i = 1; $i <= 60; $i++) { printf("line-%02d\n", $i); } exit(3);');

        try {
            $this->build()->run($noisy)->zip();
            self::fail('Expected the build to fail.');
        } catch (BuildException $e) {
            self::assertStringContainsString('line-01', $e->getMessage());
            self::assertStringContainsString('line-60', $e->getMessage());
        }
    }

    public function test_a_runner_that_cannot_stream_prints_the_output_when_the_command_ends(): void
    {
        $runner = new class implements CommandRunner {
            public function run(string $command, string $workingDirectory): array
            {
                return ['exitCode' => 0, 'output' => "captured-one\ncaptured-two\n"];
            }
        };

        $this->build()->withRunner($runner)->verbose()->run('anything')->zip();

        self::assertStringContainsString("    | captured-one\n    | captured-two", implode("\n", $this->lines));
    }

    public function test_the_config_file_can_turn_it_on(): void
    {
        file_put_contents($this->project . '/wptoolkit.json', (string) json_encode([
            'verbose' => true,
            'run' => [$this->script('configured', 'echo "from-config\n";')],
            'zip' => 'dist/out.zip',
        ]));

        [$build, $zip] = JsonConfig::load($this->project);
        $build->withOutput(function (string $line): void {
            $this->lines[] = $line;
        })->zip($zip);

        self::assertStringContainsString('| from-config', implode("\n", $this->lines));
    }

    private function build(): Build
    {
        return Build::plugin($this->project)->withOutput(function (string $line): void {
            $this->lines[] = $line;
        });
    }

    /**
     * A command that runs a small PHP script: no shell quoting to get wrong on any platform.
     */
    private function script(string $name, string $body): string
    {
        $file = dirname($this->project) . '/' . $name . '.php';
        file_put_contents($file, "<?php\n" . $body);

        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    }
}
