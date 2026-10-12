<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Throwable;
use ZipArchive;

/**
 * Writes a staged project as a reproducible zip with a `.sha256` next to it.
 *
 * The zip is written under a temporary name and renamed into place only when it is complete, so
 * whatever reads the target sees the previous zip or the new one, never half of one — and a build
 * that fails or is stopped while packaging leaves the previous zip untouched.
 */
final class ZipPackager
{
    /** 1980-01-01, the earliest time a zip entry can hold. */
    private const FIXED_MTIME = 315532800;

    public function write(BuildContext $context, string $zipPath): BuildReport
    {
        if (!is_dir(dirname($zipPath)) && !mkdir(dirname($zipPath), 0777, true) && !is_dir(dirname($zipPath))) {
            throw new BuildException(sprintf('Cannot create %s.', dirname($zipPath)));
        }

        $staged = StagedFiles::of($context->staging);
        $prefix = $context->project->slug . '/';
        $suffix = '.tmp-' . bin2hex(random_bytes(4));
        $temporary = [$zipPath . $suffix, $zipPath . '.sha256' . $suffix];

        try {
            $this->archive($context, $staged, $prefix, $temporary[0]);

            $sha256 = (string) hash_file('sha256', $temporary[0]);
            file_put_contents($temporary[1], $sha256 . '  ' . basename($zipPath) . PHP_EOL);

            // From here the output is complete; only the two renames are left.
            if (!rename($temporary[0], $zipPath) || !rename($temporary[1], $zipPath . '.sha256')) {
                throw new BuildException(sprintf('Cannot write %s (is it open in another program?).', $zipPath));
            }
        } catch (Throwable $failure) {
            foreach ($temporary as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            throw $failure;
        }

        return new BuildReport(
            $zipPath,
            $sha256,
            $context->version,
            $staged->bytes,
            array_map(static fn (string $f): string => $prefix . $f, array_keys($staged->files)),
            $staged->sizes,
            $context->warnings
        );
    }

    private function archive(BuildContext $context, StagedFiles $staged, string $prefix, string $path): void
    {
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BuildException(sprintf('Cannot write %s.', $path));
        }

        // A single folder named after the slug, the way WordPress expects plugin/theme zips.
        // Fixed timestamps and permissions: the same sources produce the same bytes, so a checksum
        // proves what was released. libzip also reads the file's own mtime, hence the touch().
        try {
            foreach ($staged->files as $relative => $absolute) {
                $context->checkpoint();
                $name = $prefix . $relative;
                touch($absolute, self::FIXED_MTIME);
                if (!$archive->addFile($absolute, $name)) {
                    throw new BuildException(sprintf('Cannot add %s to the zip.', $relative));
                }
                $archive->setMtimeName($name, self::FIXED_MTIME);
                $archive->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
            }
        } catch (Throwable $failure) {
            $archive->unchangeAll();
            @$archive->close();
            throw $failure;
        }

        if (!$archive->close()) {
            throw new BuildException(sprintf('Cannot finish writing %s.', $path));
        }
    }
}
