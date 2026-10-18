<?php

/**
 * Generates the API reference (docs/api/) from src/ (ADR-0028).
 *
 *   php bin/generate-api-docs.php           write the reference
 *   php bin/generate-api-docs.php --check   exit 1 if the files on disk differ from the source
 *
 * Exit codes: 0 current or written, 1 out of date (--check), 2 the generator could not run.
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

use Codad5\WPToolkit\Tools\ApiDocs\Generator;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

try {
    $generator = Generator::fromConfigFile($root);
    $files = $generator->build();

    if (in_array('--check', $argv, true)) {
        $problems = $generator->drift($files);
        foreach ($problems as $problem) {
            fwrite(STDERR, $problem . PHP_EOL);
        }
        if ($problems !== []) {
            fwrite(STDERR, "The API reference is out of date. Run: composer docs\n");
            exit(1);
        }
        fwrite(STDOUT, sprintf("API reference is current (%d files).\n", count($files)));
        exit(0);
    }

    $generator->write($files);
    fwrite(STDOUT, sprintf("Wrote %d files.\n", count($files)));
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Could not generate the API reference: ' . $error->getMessage() . PHP_EOL);
    exit(2);
}
