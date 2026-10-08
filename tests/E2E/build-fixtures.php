<?php

/**
 * Builds the end-to-end fixture plugins into tests/E2E/.build/plugins/ (git-ignored).
 *
 * Each fixture is a real WordPress plugin with its own bundled copy of WPToolkit, assembled the way a
 * consumer would ship it: copy src/ + bootstrap/, set the version, and (optionally) scope it with
 * bin/scope.php. Run before `wp-env start`, because wp-env mounts these directories.
 *
 * Usage: php tests/E2E/build-fixtures.php
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$templates = __DIR__ . '/plugins';
$build = __DIR__ . '/.build/plugins';

/**
 * name => [template, toolkit namespace (null = unscoped), toolkit version, guard options]
 */
$fixtures = [
    // Safe boot (ADR-0013). Each is scoped so they never interact.
    'safe-ok' => ['template' => 'app', 'namespace' => 'SafeOk\\WPToolkit', 'version' => null, 'needs' => ['php' => '8.1']],
    'safe-php99' => ['template' => 'app', 'namespace' => 'SafePhp99\\WPToolkit', 'version' => null, 'needs' => ['php' => '99.0']],
    'safe-parse-error' => ['template' => 'parse-error', 'namespace' => 'SafeParse\\WPToolkit', 'version' => null, 'needs' => []],
    'safe-boot-throws' => ['template' => 'boot-throws', 'namespace' => 'SafeThrows\\WPToolkit', 'version' => null, 'needs' => []],

    // Coexistence (ADR-0005): two scoped copies, different versions, side by side.
    'coexist-alpha' => ['template' => 'app', 'namespace' => 'Alpha\\WPToolkit', 'version' => null, 'needs' => ['toolkit' => '^1.0']],
    'coexist-beta' => ['template' => 'app', 'namespace' => 'Beta\\WPToolkit', 'version' => '1.4.0', 'needs' => ['toolkit' => '^1.4']],

    // Coexistence (ADR-0020): two unscoped copies. "first" sorts first, so WordPress loads it first
    // and its copy wins; "second" bundles 2.0.0, needs ^2.0, and must be refused / kept inert.
    'unscoped-first' => ['template' => 'app', 'namespace' => null, 'version' => null, 'needs' => ['toolkit' => '^1.0']],
    'unscoped-second' => ['template' => 'app', 'namespace' => null, 'version' => '2.0.0', 'needs' => ['toolkit' => '^2.0']],
];

remove_directory($build);

foreach ($fixtures as $name => $fixture) {
    $target = $build . '/' . $name;
    $toolkit = $target . '/lib/wptoolkit';

    copy_directory($templates . '/' . $fixture['template'], $target);
    copy_directory($root . '/src', $toolkit . '/src');
    copy_directory($root . '/bootstrap', $toolkit . '/bootstrap');
    copy_directory($root . '/languages', $toolkit . '/languages');

    if ($fixture['version'] !== null) {
        $application = $toolkit . '/src/Foundation/Application.php';
        $source = (string) file_get_contents($application);
        file_put_contents($application, (string) preg_replace("/VERSION = '[^']+'/", "VERSION = '{$fixture['version']}'", $source));
    }

    $namespace = $fixture['namespace'] ?? 'Codad5\\WPToolkit';
    if ($fixture['namespace'] !== null) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/scope.php') . ' '
            . escapeshellarg($namespace) . ' ' . escapeshellarg($toolkit) . ' 2>&1';
        exec($command, $output, $code);
        if ($code !== 0) {
            fwrite(STDERR, "Scoping {$name} failed:\n" . implode("\n", $output) . "\n");
            exit(1);
        }
    }

    // Templates are *.php.tpl (they aren't valid PHP until filled in): fill in and rename.
    $replacements = [
        '{{NAME}}' => $name,
        '{{ID}}' => str_replace(' ', '', ucwords(str_replace('-', ' ', $name))),
        '{{TITLE}}' => 'Fixture ' . $name,
        '{{NS}}' => $namespace,
        '{{NEEDS}}' => var_export($fixture['needs'] + ['name' => 'Fixture ' . $name, 'autoload' => 'standalone', 'rethrow' => false], true),
    ];
    $templateFiles = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (str_ends_with($file->getFilename(), '.php.tpl')) {
            $templateFiles[] = $file->getPathname();
        }
    }
    foreach ($templateFiles as $path) {
        file_put_contents(substr($path, 0, -4), strtr((string) file_get_contents($path), $replacements));
        unlink($path);
    }

    fwrite(STDOUT, "built {$name} (" . ($fixture['namespace'] ?? 'unscoped') . ', ' . ($fixture['version'] ?? 'current') . ")\n");
}

// The todo example, installed as `composer install` would: the library in vendor/codad5/wptoolkit,
// and a vendor/autoload.php mapping both namespaces (the guard detects and loads it).
$todo = $build . '/wptk-todo';
copy_directory($root . '/examples/todo', $todo);
foreach (['src', 'bootstrap', 'languages', 'resources'] as $folder) {
    copy_directory($root . '/' . $folder, $todo . '/vendor/codad5/wptoolkit/' . $folder);
}
file_put_contents($todo . '/vendor/autoload.php', <<<'PHP'
<?php
spl_autoload_register(static function (string $class): void {
    $map = ['WptkTodo\\' => __DIR__ . '/../src/', 'Codad5\\WPToolkit\\' => __DIR__ . '/codad5/wptoolkit/src/'];
    foreach ($map as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
PHP);
fwrite(STDOUT, "built wptk-todo (examples/todo, composer layout)\n");

function copy_directory(string $from, string $to): void
{
    if (!is_dir($to)) {
        mkdir($to, 0777, true);
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $destination = $to . substr($item->getPathname(), strlen($from));
        if ($item->isDir()) {
            if (!is_dir($destination)) {
                mkdir($destination, 0777, true);
            }
        } else {
            copy($item->getPathname(), $destination);
        }
    }
}

function remove_directory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
