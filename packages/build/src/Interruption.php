<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * A request to stop the build: Ctrl+C, a termination signal, or a condition the caller supplies.
 *
 * A signal only sets a flag. The build looks at it at safe points — between steps, several times a
 * second while a command runs, and before each file it writes — and stops by throwing, so the usual
 * cleanup runs: the command is terminated, staging and half-written output are removed, the lock is
 * released.
 *
 * What can be caught depends on the platform: SIGINT and SIGTERM where the pcntl extension is
 * loaded, Ctrl+C and Ctrl+Break on Windows. A process that is killed outright can't clean up; the
 * next build sweeps what it left.
 */
final class Interruption
{
    private bool $triggered = false;

    private bool $installed = false;

    /** @var (Closure(): bool)|null */
    private ?Closure $condition = null;

    /** @var (Closure(int): void)|null */
    private ?Closure $windowsHandler = null;

    /**
     * Stop the build when `$condition` returns true (a "cancel" file appeared, a UI asked for it).
     *
     * @param Closure(): bool $condition Checked at every safe point; keep it cheap.
     */
    public static function when(Closure $condition): self
    {
        $interruption = new self();
        $interruption->condition = $condition;

        return $interruption;
    }

    /**
     * Start listening for Ctrl+C / termination. Safe to call where neither can be caught.
     */
    public function install(): void
    {
        if ($this->installed) {
            return;
        }
        $this->installed = true;

        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach ([SIGINT, SIGTERM] as $signal) {
                pcntl_signal($signal, function (): void {
                    $this->trigger();
                });
            }
        } elseif (function_exists('sapi_windows_set_ctrl_handler')) {
            $this->windowsHandler = function (int $event): void {
                $this->trigger();
            };
            sapi_windows_set_ctrl_handler($this->windowsHandler, true);
        }
    }

    public function uninstall(): void
    {
        if (!$this->installed) {
            return;
        }
        $this->installed = false;

        if (function_exists('pcntl_signal')) {
            foreach ([SIGINT, SIGTERM] as $signal) {
                pcntl_signal($signal, SIG_DFL);
            }
        } elseif ($this->windowsHandler !== null && function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler($this->windowsHandler, false);
            $this->windowsHandler = null;
        }
    }

    public function trigger(): void
    {
        $this->triggered = true;
    }

    /**
     * @throws BuildException When the build has been asked to stop.
     */
    public function check(): void
    {
        if ($this->triggered || ($this->condition !== null && ($this->condition)())) {
            $this->triggered = true;
            throw new BuildException('Build interrupted. Nothing was written, and the temporary copy was removed.');
        }
    }
}
