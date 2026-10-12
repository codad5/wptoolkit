<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Writes a staged project as a folder (`Build::toDirectory()`): for rsync or SVN deploys, or to
 * look at exactly what a build ships.
 *
 * Replacing a folder deletes what was in it, so the target is checked before any step of the build
 * runs (`assertSafe()`): it must not be the project or contain it, and if it already exists it must
 * be empty or hold an earlier build of this same project.
 */
final class DirectoryPackager
{
    /**
     * @throws BuildException When writing there could destroy something that isn't a previous build.
     */
    public function assertSafe(Project $project, string $target): void
    {
        $target = self::normalize($target);
        $root = self::normalize($project->root);

        // Compared without case, and through realpath() when the folder exists: on Windows and macOS
        // `C:/Sites/Plugin` and `c:/sites/plugin` are one folder, and so is a symlink to it. Treating
        // two folders that differ only by case as the same one costs a refusal; the opposite mistake
        // would empty the project.
        $same = static fn (string $path): string => strtolower(self::normalize((string) (realpath($path) ?: $path)));
        if ($same($target) === $same($root) || str_starts_with($same($root) . '/', $same($target) . '/')) {
            throw new BuildException(sprintf('toDirectory("%s") is the project itself or a folder above it; choose a folder for the output only.', $target));
        }
        if (!file_exists($target)) {
            return;
        }
        if (!is_dir($target)) {
            throw new BuildException(sprintf('toDirectory("%s") is a file, not a directory.', $target));
        }

        // An earlier build has this project's own main file (plugin file, style.css) at its top.
        $main = $target . '/' . $project->relativeHeaderFile();
        $isEarlierBuild = match ($project->type) {
            'plugin' => Project::readHeader($main, 'Plugin Name') !== null,
            'theme' => Project::readHeader($main, 'Theme Name') !== null,
            default => is_file($main),
        };
        $isEmpty = !(new FilesystemIterator($target))->valid();
        if (!$isEmpty && !$isEarlierBuild) {
            throw new BuildException(sprintf(
                'toDirectory("%s") is not empty and is not an earlier build of this project, so it was left alone. Empty it or choose another folder.',
                $target
            ));
        }
    }

    public function write(BuildContext $context, string $target): BuildReport
    {
        $target = self::normalize($target);
        $this->assertSafe($context->project, $target);

        self::empty($target);
        $staged = StagedFiles::of($context->staging);
        foreach ($staged->files as $relative => $absolute) {
            $destination = $target . '/' . $relative;
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0777, true) && !is_dir(dirname($destination))) {
                throw new BuildException(sprintf('Cannot create %s.', dirname($destination)));
            }
            if (!copy($absolute, $destination)) {
                throw new BuildException(sprintf('Cannot write %s.', $destination));
            }
        }

        return new BuildReport('', '', $context->version, $staged->bytes, array_keys($staged->files), $staged->sizes, $context->warnings, $target);
    }

    /**
     * Forward slashes, no trailing slash, and `.` / `..` resolved — so `out/..` or `./` can't pass
     * for a different folder than the one they point at. The path need not exist.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = preg_match('#^([a-z]:)?/#i', $path, $m) === 1 ? $m[0] : '';
        $segments = [];
        foreach (explode('/', substr($path, strlen($prefix))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return rtrim($prefix . implode('/', $segments), '/');
    }

    private static function empty(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
}
