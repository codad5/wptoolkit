<?php

/**
 * Syntax-check every PHP file under the given directories with the running PHP binary.
 *
 * Usage: php bin/lint.php <dir> [<dir>...]
 * Exits 1 if any file fails `php -l`. No dependencies, so it runs before `composer install`.
 */

declare(strict_types=1);

$dirs = array_slice($argv, 1) ?: ['src'];
$failed = 0;
$checked = 0;

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $checked++;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $failed++;
            fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        }
        $output = [];
    }
}

fwrite(STDOUT, sprintf('Linted %d file(s), %d failed.%s', $checked, $failed, PHP_EOL));
exit($failed > 0 ? 1 : 0);
