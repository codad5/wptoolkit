<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\Progress;
use PHPUnit\Framework\TestCase;

/**
 * Build output: plain lines everywhere, plus a spinner that exists only on an interactive terminal
 * and never ends up in a log.
 */
final class ProgressTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    private float $now = 100.0;

    public function test_plain_output_is_one_line_per_event_with_step_durations(): void
    {
        $progress = $this->plain();

        $progress->stepStarted('run');
        $progress->line('  $ npm run build');
        $this->now += 12.44;
        $progress->stepFinished();
        $progress->stepStarted('verify');
        $this->now += 0.02;
        $progress->stepFinished();

        self::assertSame(['→ run', '  $ npm run build', '  ✓ 12.4s', '→ verify', '  ✓ 0.0s'], $this->lines);
    }

    public function test_long_steps_are_shown_in_minutes(): void
    {
        $progress = $this->plain();
        $progress->stepStarted('run');
        $this->now += 125.3;
        $progress->stepFinished();

        self::assertSame('  ✓ 2m 05s', $this->lines[1]);
    }

    public function test_plain_output_contains_no_terminal_control_codes_even_when_ticked(): void
    {
        $progress = $this->plain();
        $progress->stepStarted('run');
        $progress->activity('npm run build');
        $progress->tick();
        $progress->tick();
        $progress->activity(null);
        $progress->stepFinished();

        self::assertStringNotContainsString("\r", implode("\n", $this->lines));
        self::assertStringNotContainsString("\033", implode("\n", $this->lines));
        self::assertCount(2, $this->lines, 'ticks print nothing without a terminal');
    }

    public function test_a_failed_step_is_marked_and_keeps_its_duration(): void
    {
        $progress = $this->plain();
        $progress->stepStarted('run');
        $this->now += 3;
        $progress->stepFailed();

        self::assertSame(['→ run', '  ✗ 3.0s'], $this->lines);
    }

    public function test_quiet_prints_only_warnings(): void
    {
        $progress = new Progress(fn (string $line) => $this->lines[] = $line, quiet: true, clock: fn (): float => $this->now);

        $progress->stepStarted('run');
        $progress->line('progress');
        $progress->warn('read me');
        $progress->stepFinished();

        self::assertSame(['  ! read me'], $this->lines);
    }

    public function test_an_interactive_terminal_gets_a_spinner_that_leaves_the_same_lines_behind(): void
    {
        $terminal = fopen('php://memory', 'w+');
        self::assertIsResource($terminal);
        $progress = new Progress(static function (): void {
        }, terminal: $terminal, clock: fn (): float => $this->now);

        $progress->stepStarted('run');
        $progress->line('  $ npm run build');
        $progress->activity('npm run build');
        $progress->tick();
        $this->now += 3.2;
        $progress->tick();
        $progress->activity(null);
        $this->now += 1;
        $progress->stepFinished();

        rewind($terminal);
        $raw = (string) stream_get_contents($terminal);
        self::assertStringContainsString("\r", $raw, 'the spinner line is redrawn in place');
        self::assertMatchesRegularExpression('/npm run build\s+3s/', $raw, 'it shows what is running and for how long');

        self::assertSame(
            ['→ run', '  $ npm run build', '  ✓ 4.2s'],
            $this->whatStaysOnScreen($raw),
            'once the step ends, the screen shows exactly what a log would'
        );
    }

    public function test_the_spinner_advances_and_falls_back_to_ascii_frames(): void
    {
        $terminal = fopen('php://memory', 'w+');
        self::assertIsResource($terminal);
        $progress = new Progress(static function (): void {
        }, terminal: $terminal, unicode: false, clock: fn (): float => $this->now);

        $progress->stepStarted('run');
        $progress->activity('build');
        foreach (range(1, 4) as $ignored) {
            $this->now += 0.2;
            $progress->tick();
        }

        rewind($terminal);
        $raw = (string) stream_get_contents($terminal);
        self::assertMatchesRegularExpression('#[|/\\\\-] build#', $raw);
        self::assertGreaterThan(1, count(array_unique(array_filter(['|', '/', '-', '\\'], static fn (string $f): bool => str_contains($raw, $f . ' build')))));
        self::assertDoesNotMatchRegularExpression('/[^\x00-\x7F]/', str_replace(['→', '✓'], '', $raw), 'only the two status marks are non-ASCII');
    }

    public function test_a_step_whose_output_is_streamed_has_no_spinner(): void
    {
        $terminal = fopen('php://memory', 'w+');
        self::assertIsResource($terminal);
        $progress = new Progress(static function (): void {
        }, terminal: $terminal, clock: fn (): float => $this->now);

        $progress->stepStarted('run');
        $progress->activity(null); // streamed: the command's own lines are the progress
        $progress->line('    | compiling');
        $progress->tick();
        $progress->stepFinished();

        rewind($terminal);
        self::assertStringNotContainsString("\r", (string) stream_get_contents($terminal));
    }

    public function test_a_build_with_its_own_output_handler_never_gets_a_spinner(): void
    {
        $project = sys_get_temp_dir() . '/wptoolkit-progress-' . bin2hex(random_bytes(4)) . '/my-plugin';
        mkdir($project, 0777, true);
        file_put_contents($project . '/my-plugin.php', "<?php\n/*\n * Plugin Name: My Plugin\n * Version: 1.0.0\n */\n");

        try {
            Build::plugin($project)->withOutput(function (string $line): void {
                $this->lines[] = $line;
            })->zip('dist/out.zip');
        } finally {
            foreach (['/dist/out.zip', '/dist/out.zip.sha256', '/my-plugin.php'] as $file) {
                @unlink($project . $file);
            }
            @rmdir($project . '/dist');
            rmdir($project);
            rmdir(dirname($project));
        }

        // The summary is one multi-line string joined with PHP_EOL, so "\r\n" is fine on Windows; a
        // carriage return on its own would be a spinner redraw.
        $output = str_replace("\r\n", "\n", implode("\n", $this->lines));
        self::assertStringNotContainsString("\r", $output);
        self::assertStringNotContainsString("\033", $output);
        self::assertStringContainsString("→ verify\n  ✓ ", $output);
    }

    private function plain(): Progress
    {
        return new Progress(function (string $line): void {
            $this->lines[] = $line;
        }, clock: fn (): float => $this->now);
    }

    /**
     * What a terminal shows after the raw bytes are played back: `\r` returns to the start of the
     * line and `ESC[2K` clears it.
     *
     * @return list<string>
     */
    private function whatStaysOnScreen(string $raw): array
    {
        $screen = [];
        foreach (explode("\n", $raw) as $line) {
            $parts = explode("\r", $line);
            $last = (string) end($parts);
            $screen[] = str_replace("\033[2K", '', $last);
        }

        return array_values(array_filter($screen, static fn (string $line): bool => $line !== ''));
    }
}
