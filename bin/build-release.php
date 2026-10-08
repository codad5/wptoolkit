<?php

/**
 * Builds WPToolkit's standalone zip (ADR-0014) with WPToolkit's own build tool:
 * dist/wptoolkit-{version}.zip and its .sha256.
 *
 * The zip holds what a plugin without Composer bundles: src/, bootstrap/, languages/, resources/,
 * bin/scope.php (the zero-tool scoper), README.md, llms.txt and LICENSE — under one wptoolkit/ folder.
 *
 * Usage: php bin/build-release.php [--expect=1.0.0]
 *   --expect  Fail unless Application::VERSION is exactly this (the release workflow passes the tag).
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\ProcessCommandRunner;
use Codad5\WPToolkit\Build\Version;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$options = getopt('', ['expect:']);
$expected = isset($options['expect']) && is_string($options['expect']) ? ltrim($options['expect'], 'v') : null;

$build = Build::library($root, 'wptoolkit', 'src/Foundation/Application.php')
    ->version(Version::fromConstant('VERSION'))
    // Everything at the top level goes, except the runtime folders (gitignore negation).
    ->exclude(
        '/*',
        '!/src',
        '!/bootstrap',
        '!/languages',
        '!/resources',
        '!/LICENSE',
        '!/llms.txt',
        '!/bin',
        '/bin/*',
        '!/bin/scope.php'
    )
    ->include('/README.md')
    ->verify(maxSizeMb: 5);

try {
    $version = Version::fromConstant('VERSION')->resolve($build->project(), new ProcessCommandRunner());
    if ($expected !== null && $version !== $expected) {
        throw new BuildException(sprintf('Application::VERSION is %s but the release is %s. Run: php bin/set-version.php %s', $version, $expected, $expected));
    }

    $build->zip('dist/wptoolkit-{version}.zip');
} catch (BuildException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
