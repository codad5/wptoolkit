<?php

/**
 * Sets the library version everywhere it is written down, so they can't drift:
 * Application::VERSION (PHP) and VERSION in resources/js/client.js (the JS client registers its
 * factory under it, and PHP looks it up by Application::VERSION).
 *
 * Usage: php bin/set-version.php 1.0.0
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

$version = ltrim((string) ($argv[1] ?? ''), 'v');
if (preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.\-]+)?$/', $version) !== 1) {
    fwrite(STDERR, "Usage: php bin/set-version.php 1.2.3[-rc.1]\n");
    exit(2);
}

$root = dirname(__DIR__);
$files = [
    'src/Foundation/Application.php' => "/(public const VERSION = ')[^']+(';)/",
    'resources/js/client.js' => "/(var VERSION = ')[^']+(';)/",
];

foreach ($files as $file => $pattern) {
    $path = $root . '/' . $file;
    $source = (string) file_get_contents($path);
    $updated = preg_replace($pattern, '${1}' . $version . '${2}', $source, 1, $count);
    if ($count !== 1 || !is_string($updated)) {
        fwrite(STDERR, "Version not found in {$file}\n");
        exit(1);
    }
    file_put_contents($path, $updated);
    fwrite(STDOUT, "{$file}: {$version}\n");
}
