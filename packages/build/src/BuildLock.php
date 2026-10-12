<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * One build per project at a time. Two builds of the same project would run their commands in the
 * same folder and write the same output file.
 *
 * It is an operating-system file lock, which the system drops when the process ends however it
 * ends — so a build that crashes or is killed never leaves a lock to clear by hand. The lock file
 * lives in the system temp directory, not in the project, so it is never shipped or committed.
 */
final class BuildLock
{
    /** Seconds between attempts while waiting. */
    private const RETRY_EVERY = 0.2;

    /**
     * @param resource|null $handle Null once released.
     */
    private function __construct(private mixed $handle, private readonly string $file)
    {
    }

    /**
     * The lock file for a project: the same one however the project's path is spelled.
     */
    public static function path(Project $project): string
    {
        $root = strtolower(DirectoryPackager::normalize((string) (realpath($project->root) ?: $project->root)));

        return sys_get_temp_dir() . '/wptoolkit-build-locks/' . sha1($root) . '.lock';
    }

    /**
     * @param int $waitSeconds 0 fails at once if another build holds the lock.
     * @param (Closure(): void)|null $whileWaiting Called between attempts (to notice an interruption).
     * @throws BuildException When the lock can't be taken in time.
     */
    public static function acquire(Project $project, int $waitSeconds = 0, ?Closure $whileWaiting = null): self
    {
        $file = self::path($project);
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0777, true) && !is_dir(dirname($file))) {
            throw new BuildException(sprintf('Cannot create %s for the build lock.', dirname($file)));
        }

        $handle = fopen($file, 'c');
        if ($handle === false) {
            throw new BuildException(sprintf('Cannot open the build lock %s.', $file));
        }

        $deadline = microtime(true) + $waitSeconds;
        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $deadline) {
                fclose($handle);
                throw new BuildException(self::busyMessage($file, $waitSeconds));
            }
            if ($whileWaiting !== null) {
                try {
                    $whileWaiting();
                } catch (\Throwable $stop) {
                    fclose($handle);
                    throw $stop;
                }
            }
            usleep((int) (self::RETRY_EVERY * 1_000_000));
        }

        // Who holds it, for the other build's message. A separate file: on Windows a locked file
        // can't be read by another process.
        file_put_contents($file . '.json', (string) json_encode(['pid' => getmypid(), 'started' => date('H:i:s')]));

        return new self($handle, $file);
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        @unlink($this->file . '.json');
        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    private static function busyMessage(string $file, int $waited): string
    {
        $info = is_file($file . '.json') ? json_decode((string) file_get_contents($file . '.json'), true) : null;
        $who = is_array($info) && isset($info['pid'], $info['started'])
            ? sprintf(' (process %d, started at %s)', (int) $info['pid'], (string) $info['started'])
            : '';

        return sprintf(
            'Another build of this project is running%s%s. Wait for it to finish, or use waitForLock() / --wait=SECONDS.',
            $who,
            $waited > 0 ? sprintf(' and did not finish within %d seconds', $waited) : ''
        );
    }
}
