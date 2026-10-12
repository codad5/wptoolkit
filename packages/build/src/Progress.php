<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * What a build prints. Everywhere, that is one plain line per event:
 *
 *     → run
 *       $ npm run build
 *       ✓ 12.4s
 *
 * On an interactive terminal there is also a spinner under the current step, showing what is
 * running and for how long. It is drawn in place and erased when the step ends, so the screen ends
 * up showing exactly what a log would — and nothing but plain lines is ever written when the output
 * is a file, a pipe, CI, or a handler the caller supplied.
 */
final class Progress
{
    private const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    private const ASCII_FRAMES = ['|', '/', '-', '\\'];

    /** Seconds between redraws: often enough to look alive, rare enough not to flicker. */
    private const REDRAW_EVERY = 0.08;

    private const ERASE_LINE = "\r\033[2K";

    private float $stepStartedAt = 0.0;

    private ?string $activity = null;

    private float $activityStartedAt = 0.0;

    private float $lastDrawnAt = 0.0;

    private int $frame = 0;

    private bool $spinnerShown = false;

    /** @var Closure(): float */
    private readonly Closure $clock;

    /**
     * @param Closure(string): void $output Receives each plain line (without a line ending).
     * @param resource|null $terminal An interactive terminal to draw on; null for plain output.
     * @param bool $unicode False where braille spinner frames don't render (older Windows consoles).
     * @param (Closure(): float)|null $clock Seconds, for tests.
     */
    public function __construct(
        private readonly Closure $output,
        private readonly mixed $terminal = null,
        private readonly bool $unicode = true,
        private readonly bool $quiet = false,
        ?Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    public function line(string $line): void
    {
        if (!$this->quiet) {
            $this->write($line);
        }
    }

    /**
     * Printed even when quiet: something a person should read.
     */
    public function warn(string $line): void
    {
        $this->write('  ! ' . $line);
    }

    public function stepStarted(string $name): void
    {
        $this->stepStartedAt = ($this->clock)();
        $this->line('→ ' . $name);
    }

    public function stepFinished(): void
    {
        $this->endStep('✓');
    }

    public function stepFailed(): void
    {
        $this->endStep('✗');
    }

    /**
     * What the step is waiting on now (a command), or null when nothing should spin — including
     * while a command's own output is being streamed, which is progress enough.
     */
    public function activity(?string $label): void
    {
        $this->erase();
        $this->activity = $label;
        $this->activityStartedAt = ($this->clock)();
        $this->draw();
    }

    /**
     * Called several times a second while something runs. Does nothing without a terminal.
     */
    public function tick(): void
    {
        if ($this->terminal === null || $this->activity === null || $this->quiet) {
            return;
        }
        if (($this->clock)() - $this->lastDrawnAt >= self::REDRAW_EVERY) {
            $this->frame++;
            $this->draw();
        }
    }

    public static function duration(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        return sprintf('%dm %02ds', intdiv((int) $seconds, 60), (int) $seconds % 60);
    }

    private function endStep(string $mark): void
    {
        $this->erase();
        $this->activity = null;
        $this->line('  ' . $mark . ' ' . self::duration(($this->clock)() - $this->stepStartedAt));
    }

    private function write(string $line): void
    {
        if ($this->terminal === null) {
            ($this->output)($line);
            return;
        }

        $this->erase();
        fwrite($this->terminal, $line . "\n");
        $this->draw();
    }

    private function draw(): void
    {
        if ($this->terminal === null || $this->activity === null || $this->quiet) {
            return;
        }

        $frames = $this->unicode ? self::FRAMES : self::ASCII_FRAMES;
        $elapsed = (int) (($this->clock)() - $this->activityStartedAt);
        $time = $elapsed < 60 ? $elapsed . 's' : sprintf('%dm %02ds', intdiv($elapsed, 60), $elapsed % 60);

        fwrite($this->terminal, self::ERASE_LINE . '  ' . $frames[$this->frame % count($frames)] . ' ' . $this->activity . '  ' . $time);
        $this->spinnerShown = true;
        $this->lastDrawnAt = ($this->clock)();
    }

    private function erase(): void
    {
        if ($this->spinnerShown && $this->terminal !== null) {
            fwrite($this->terminal, self::ERASE_LINE);
            $this->spinnerShown = false;
        }
    }
}
