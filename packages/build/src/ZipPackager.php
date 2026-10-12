<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use ZipArchive;

/**
 * Writes a staged project as a reproducible zip with a `.sha256` next to it.
 */
final class ZipPackager
{
    /** 1980-01-01, the earliest time a zip entry can hold. */
    private const FIXED_MTIME = 315532800;

    public function write(BuildContext $context, string $zipPath): BuildReport
    {
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0777, true);
        }
        if (is_file($zipPath)) {
            unlink($zipPath);
        }

        $staged = StagedFiles::of($context->staging);

        $archive = new ZipArchive();
        if ($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BuildException(sprintf('Cannot write %s.', $zipPath));
        }

        // A single folder named after the slug, the way WordPress expects plugin/theme zips.
        // Fixed timestamps and permissions: the same sources produce the same bytes, so a checksum
        // proves what was released. libzip also reads the file's own mtime, hence the touch().
        $prefix = $context->project->slug . '/';
        foreach ($staged->files as $relative => $absolute) {
            $name = $prefix . $relative;
            touch($absolute, self::FIXED_MTIME);
            $archive->addFile($absolute, $name);
            $archive->setMtimeName($name, self::FIXED_MTIME);
            $archive->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
        }
        $archive->close();

        $sha256 = (string) hash_file('sha256', $zipPath);
        file_put_contents($zipPath . '.sha256', $sha256 . '  ' . basename($zipPath) . PHP_EOL);

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
}
