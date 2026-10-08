<?php

/**
 * Boots a real WordPress for the integration suite (ADR-0010 amendment): runs inside wp-env's
 * tests-cli container, where this repository is mounted at wp-content/plugins/wptoolkit.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$directory = __DIR__;
while ($directory !== dirname($directory) && !is_file($directory . '/wp-load.php')) {
    $directory = dirname($directory);
}

if (!is_file($directory . '/wp-load.php')) {
    fwrite(STDERR, "wp-load.php not found. Run the integration suite inside wp-env: composer test:integration\n");
    exit(1);
}

require_once $directory . '/wp-load.php';
