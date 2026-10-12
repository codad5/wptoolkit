<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\BuildLock;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\Interruption;
use Codad5\WPToolkit\Build\Phase;
use Codad5\WPToolkit\Build\ProcessCommandRunner;
use Codad5\WPToolkit\Build\Project;
use PHPUnit\Framework\TestCase;

/**
 * What happens when builds overlap, are interrupted, or die: one build per project at a time, output
 * that is either the old file or the new one, and nothing left behind. These use real processes and
 * real file locks.
 */
final class BuildRobustnessTest extends TestCase
{
    private string $root;

    private string $project;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wptoolkit-robust-' . bin2hex(random_bytes(4));
        $this->project = $this->root . '/my-plugin';
        mkdir($this->project . '/src', 0777, true);
        file_put_contents($this->project . '/my-plugin.php', "<?php\n/*\n * Plugin Name: My Plugin\n * Version: 1.0.0\n */\n");
        file_put_contents($this->project . '/src/Plugin.php', '<?php // plugin');
    }

    protected function tearDown(): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    // --- One build per project -----------------------------------------------------------------

    public function test_a_second_build_of_the_same_project_is_refused_while_one_is_running(): void
    {
        $holder = $this->holdLockInAnotherProcess();

        try {
            $this->build()->zip('dist/out.zip');
            self::fail('Expected the second build to be refused.');
        } catch (BuildException $e) {
            self::assertStringContainsString('Another build of this project is running', $e->getMessage());
            self::assertMatchesRegularExpression('/process \d+/', $e->getMessage(), 'it says who holds the lock');
        } finally {
            $this->releaseHolder($holder);
        }
        self::assertFileDoesNotExist($this->project . '/dist/out.zip');
    }

    public function test_wait_for_lock_starts_as_soon_as_the_other_build_finishes(): void
    {
        $holder = $this->holdLockInAnotherProcess();
        $release = $this->root . '/release-lock';
        $checks = 0;
        // Checked while the build waits for the lock: the first time, the other build lets go. That
        // makes "it really waited, then went ahead" independent of how fast this machine is.
        $whileWaiting = Interruption::when(static function () use (&$checks, $release): bool {
            $checks++;
            touch($release);

            return false;
        });

        try {
            $this->build()->waitForLock(30)->withInterruption($whileWaiting)->zip('dist/out.zip');
        } finally {
            $this->releaseHolder($holder);
        }

        self::assertGreaterThanOrEqual(1, $checks, 'it waited for the other build instead of failing');
        self::assertFileExists($this->project . '/dist/out.zip');
    }

    public function test_the_lock_is_released_after_a_build_whether_it_succeeds_or_fails(): void
    {
        $this->build()->zip('dist/out.zip');
        try {
            $this->build()->run($this->script('fails', 'exit(2);'))->zip('dist/out.zip');
        } catch (BuildException) {
        }

        $this->build()->zip('dist/out.zip'); // would throw if either build had kept the lock
        self::assertFileExists($this->project . '/dist/out.zip');
    }

    public function test_the_lock_names_the_project_however_its_path_is_spelled(): void
    {
        self::assertSame(
            BuildLock::path(Project::plugin($this->project)),
            BuildLock::path(Project::plugin($this->project . '/src/..')),
        );
    }

    public function test_different_projects_do_not_block_each_other(): void
    {
        $other = $this->root . '/other-plugin';
        mkdir($other);
        file_put_contents($other . '/other-plugin.php', "<?php\n/*\n * Plugin Name: Other\n * Version: 1.0.0\n */\n");
        $holder = $this->holdLockInAnotherProcess();

        try {
            Build::plugin($other)->quiet()->zip('dist/out.zip');
        } finally {
            $this->releaseHolder($holder);
        }

        self::assertFileExists($other . '/dist/out.zip');
    }

    // --- Output is the old file or the new one, never half of one --------------------------------

    public function test_a_zip_build_leaves_only_the_zip_and_its_checksum(): void
    {
        $this->build()->zip('dist/out.zip');
        $this->build()->zip('dist/out.zip');

        $files = array_values(array_diff((array) scandir($this->project . '/dist'), ['.', '..']));
        self::assertSame(['out.zip', 'out.zip.sha256'], $files, 'no temporary files are left next to the output');
        self::assertStringStartsWith(hash_file('sha256', $this->project . '/dist/out.zip') . '  out.zip', (string) file_get_contents($this->project . '/dist/out.zip.sha256'));
    }

    public function test_the_previous_zip_survives_a_build_that_is_stopped_while_packaging(): void
    {
        $this->build()->zip('dist/out.zip');
        $before = hash_file('sha256', $this->project . '/dist/out.zip');
        file_put_contents($this->project . '/src/New.php', '<?php // changes the zip');

        try {
            $this->build()->withInterruption($this->stopAtTheLastCheckOf(fn (Build $build) => $build->zip('dist/probe.zip')))->zip('dist/out.zip');
            self::fail('Expected the build to stop.');
        } catch (BuildException) {
        }

        self::assertSame($before, hash_file('sha256', $this->project . '/dist/out.zip'));
        self::assertSame(
            ['out.zip', 'out.zip.sha256', 'probe.zip', 'probe.zip.sha256'],
            array_values(array_diff((array) scandir($this->project . '/dist'), ['.', '..'])),
            'nothing half-written is left next to it'
        );
    }

    public function test_a_directory_build_leaves_no_temporary_folders_beside_it(): void
    {
        $this->build()->toDirectory($this->root . '/out');
        $this->build()->toDirectory($this->root . '/out');

        $siblings = array_values(array_filter(
            array_diff((array) scandir($this->root), ['.', '..']),
            static fn (string $name): bool => str_starts_with($name, 'out')
        ));
        self::assertSame(['out'], $siblings);
        self::assertFileExists($this->root . '/out/my-plugin.php');
    }

    public function test_the_previous_directory_survives_a_build_that_is_stopped_while_writing(): void
    {
        $this->build()->toDirectory($this->root . '/out');
        file_put_contents($this->project . '/src/New.php', '<?php');

        try {
            $stop = $this->stopAtTheLastCheckOf(fn (Build $build) => $build->toDirectory($this->root . '/probe'));
            $this->build()->withInterruption($stop)->toDirectory($this->root . '/out');
            self::fail('Expected the build to stop.');
        } catch (BuildException) {
        }

        self::assertFileExists($this->root . '/out/src/Plugin.php', 'the earlier build is still there, whole');
        self::assertFileDoesNotExist($this->root . '/out/src/New.php');
        $siblings = array_values(array_filter(array_diff((array) scandir($this->root), ['.', '..']), static fn (string $n): bool => str_starts_with($n, 'out')));
        self::assertSame(['out'], $siblings, 'the half-written copy was removed');
    }

    // --- Interruption ----------------------------------------------------------------------------

    public function test_a_command_is_polled_while_it_runs_and_can_be_stopped(): void
    {
        $marker = $this->root . '/finished.txt';
        $slow = $this->script('slow', 'echo "started\n"; sleep(8); file_put_contents(' . var_export($marker, true) . ', "done");');
        $ticks = 0;
        $started = microtime(true);

        try {
            (new ProcessCommandRunner())->watch($slow, $this->root, static function (string $line): void {
            }, static function () use (&$ticks, $started): void {
                $ticks++;
                if (microtime(true) - $started > 1.0) {
                    throw new \RuntimeException('stop');
                }
            });
            self::fail('The tick callback should have stopped the command.');
        } catch (\RuntimeException $e) {
            self::assertSame('stop', $e->getMessage());
        }

        self::assertGreaterThan(3, $ticks, 'polled several times a second');
        self::assertLessThan(6, microtime(true) - $started, 'returned without waiting for the command to end');
        sleep(1);
        self::assertFileDoesNotExist($marker, 'the command was terminated, not left running');
    }

    public function test_watch_returns_the_same_result_as_run(): void
    {
        $command = $this->script('out', 'echo "one\n"; fwrite(STDERR, "two\n"); exit(3);');
        $lines = [];

        $result = (new ProcessCommandRunner())->watch($command, $this->root, static function (string $line) use (&$lines): void {
            $lines[] = $line;
        }, static function (): void {
        });

        self::assertSame(3, $result['exitCode']);
        self::assertSame(['one', 'two'], $lines);
        self::assertSame("one\ntwo\n", str_replace("\r\n", "\n", $result['output']));
    }

    public function test_an_interrupted_build_cleans_up_and_releases_the_lock(): void
    {
        $interruption = new Interruption();
        $slow = $this->script('slow', 'sleep(8);');
        $stagingBefore = $this->stagingDirectories();
        $started = microtime(true);
        $trigger = new class ($interruption) implements BuildStep {
            public function __construct(private readonly Interruption $interruption)
            {
            }

            public function name(): string
            {
                return 'ctrl-c';
            }

            public function phase(): Phase
            {
                return Phase::Prepare;
            }

            public function run(BuildContext $context): void
            {
                $this->interruption->trigger(); // as a signal handler would, just before a long command
            }
        };

        try {
            $this->build()->withInterruption($interruption)->step($trigger)->run($slow)->zip('dist/out.zip');
            self::fail('Expected the build to stop.');
        } catch (BuildException $e) {
            self::assertStringContainsString('interrupted', strtolower($e->getMessage()));
        }

        self::assertLessThan(6, microtime(true) - $started, 'the long command was not waited for');
        self::assertSame($stagingBefore, $this->stagingDirectories(), 'no staging copy is left behind');
        self::assertFileDoesNotExist($this->project . '/dist/out.zip');
        $this->build()->zip('dist/out.zip'); // the lock was released
    }

    /**
     * The real thing, where the platform can deliver it: Ctrl+C (SIGINT) sent to a running build
     * process. The child must stop quickly, take its command down with it, and leave nothing behind.
     */
    public function test_a_real_sigint_stops_the_build_and_the_command_it_started(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || !extension_loaded('pcntl') || !defined('SIGINT')) {
            self::markTestSkipped('Delivering SIGINT to a child process needs a POSIX system with the pcntl extension.');
        }

        $started = $this->root . '/started';
        $heartbeat = $this->root . '/heartbeat';
        $command = $this->script('beat', sprintf(
            'touch(%1$s); for ($i = 0; $i < 80; $i++) { usleep(250000); touch(%2$s); }',
            var_export($started, true),
            var_export($heartbeat, true)
        ));
        $runner = $this->root . '/run-build.php';
        file_put_contents($runner, sprintf(
            "<?php\nrequire %s;\n\\Codad5\\WPToolkit\\Build\\Build::plugin(%s)->quiet()->run(%s)->zip('dist/out.zip');\n",
            var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true),
            var_export($this->project, true),
            var_export($command, true)
        ));
        $stagingBefore = $this->stagingDirectories();

        // `exec` so the process we signal is PHP itself, not a shell in front of it.
        $process = proc_open('exec ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner), [], $pipes, $this->root);
        self::assertIsResource($process);
        for ($i = 0; $i < 100 && !is_file($heartbeat); $i++) {
            usleep(100000);
        }
        self::assertFileExists($heartbeat, 'the build got as far as running its command');

        $signalled = microtime(true);
        proc_terminate($process, SIGINT);
        for ($i = 0; $i < 100 && proc_get_status($process)['running']; $i++) {
            usleep(100000);
        }
        $stopped = !proc_get_status($process)['running'];
        if (!$stopped) {
            proc_terminate($process, SIGKILL);
        }
        proc_close($process);

        self::assertTrue($stopped, 'the build process ended after SIGINT');
        self::assertLessThan(8, microtime(true) - $signalled, 'and promptly, not after the 20 second command');

        $last = (int) filemtime($heartbeat);
        sleep(2);
        clearstatcache();
        self::assertSame($last, (int) filemtime($heartbeat), 'the command it started is no longer running');
        self::assertSame($stagingBefore, $this->stagingDirectories(), 'no staging copy is left behind');
        self::assertFileDoesNotExist($this->project . '/dist/out.zip');
        $this->build()->zip('dist/out.zip'); // and the lock was released
    }

    public function test_staging_left_by_builds_that_were_killed_is_swept_but_recent_ones_are_kept(): void
    {
        $old = sys_get_temp_dir() . '/wptoolkit-build-' . bin2hex(random_bytes(6));
        $recent = sys_get_temp_dir() . '/wptoolkit-build-' . bin2hex(random_bytes(6));
        foreach ([$old, $recent] as $directory) {
            mkdir($directory . '/some-plugin', 0777, true);
            file_put_contents($directory . '/some-plugin/file.php', '<?php');
        }
        touch($old, time() - 2 * 86400);

        try {
            $this->build()->zip('dist/out.zip');

            self::assertDirectoryDoesNotExist($old);
            self::assertDirectoryExists($recent, 'it may belong to a build that is running now');
        } finally {
            @unlink($recent . '/some-plugin/file.php');
            @rmdir($recent . '/some-plugin');
            @rmdir($recent);
        }
    }

    private function build(): Build
    {
        return Build::plugin($this->project)->quiet();
    }

    /**
     * An interruption that fires at the very last point a build checks for one — which is while the
     * output is being written, file by file. A first run of the same build counts the checks.
     *
     * @param \Closure(Build): mixed $sameBuild
     */
    private function stopAtTheLastCheckOf(\Closure $sameBuild): Interruption
    {
        $total = 0;
        $sameBuild($this->build()->withInterruption(Interruption::when(static function () use (&$total): bool {
            $total++;
            return false;
        })));
        self::assertGreaterThan(2, $total, 'the build checks for interruption while it writes');

        $seen = 0;
        return Interruption::when(static function () use (&$seen, $total): bool {
            return ++$seen >= $total;
        });
    }

    /**
     * A process that takes this project's build lock and keeps it until releaseHolder() — however long
     * the test takes — so no test depends on a timer. It runs from the system temp directory, not
     * from the test's folder, so that folder can be deleted while it is still shutting down.
     *
     * @return resource
     */
    private function holdLockInAnotherProcess()
    {
        $release = $this->root . '/release-lock';
        $script = $this->root . '/hold.php';
        file_put_contents($script, strtr(<<<'PHP'
            <?php
            require {AUTOLOAD};
            $lock = \Codad5\WPToolkit\Build\BuildLock::acquire(\Codad5\WPToolkit\Build\Project::plugin({PROJECT}));
            echo "held\n";
            for ($i = 0; $i < 1200 && !is_file({RELEASE}); $i++) {
                usleep(100000);
            }
            $lock->release();
            PHP, [
            '{AUTOLOAD}' => var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true),
            '{PROJECT}' => var_export($this->project, true),
            '{RELEASE}' => var_export($release, true),
        ]));

        $process = proc_open(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), [1 => ['pipe', 'w']], $pipes, sys_get_temp_dir());
        self::assertIsResource($process);
        self::assertSame("held", trim((string) fgets($pipes[1])), 'the other process took the lock');

        return $process;
    }

    /**
     * @param resource $holder
     */
    private function releaseHolder($holder): void
    {
        touch($this->root . '/release-lock');
        proc_close($holder);
    }

    /**
     * @return list<string>
     */
    private function stagingDirectories(): array
    {
        $found = glob(sys_get_temp_dir() . '/wptoolkit-build-*', GLOB_ONLYDIR) ?: [];
        sort($found);

        return array_values(array_filter($found, static fn (string $d): bool => preg_match('/wptoolkit-build-[0-9a-f]{12}$/', $d) === 1));
    }

    private function script(string $name, string $body): string
    {
        $file = $this->root . '/' . $name . '.php';
        file_put_contents($file, "<?php\n" . $body);

        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file);
    }
}
