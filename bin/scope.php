<?php

/**
 * Scope a standalone copy of WPToolkit into your own namespace, with nothing but the PHP CLI
 * (ADR-0005, ADR-0014).
 *
 *   php bin/scope.php "MyPlugin\WPToolkit" [path/to/wptoolkit]
 *
 * Rewrites every namespace, `use` and class reference that starts with `Codad5\WPToolkit` in the
 * copy's src/ and bootstrap/ — in place. Strings are left alone (the library never names
 * its own classes in strings), so the coexistence ledger stays shared between copies.
 *
 * Exit codes: 0 rewritten, 1 bad arguments, 2 nothing to rewrite (already scoped?).
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

const ORIGINAL = 'Codad5\\WPToolkit';

$target = $argv[1] ?? '';
$copy = rtrim($argv[2] ?? dirname(__DIR__), '/\\');

if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $target) !== 1) {
    fwrite(STDERR, "Usage: php bin/scope.php \"MyPlugin\\WPToolkit\" [path/to/wptoolkit]\n");
    exit(1);
}

if (strcasecmp($target, ORIGINAL) === 0) {
    fwrite(STDERR, "The new namespace must differ from " . ORIGINAL . ".\n");
    exit(1);
}

$files = 0;
$references = 0;

foreach (['src', 'bootstrap'] as $folder) {
    $root = $copy . '/' . $folder;
    if (!is_dir($root)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $source = (string) file_get_contents($path);
        [$rewritten, $count] = rewrite($source, $target);

        if ($count > 0) {
            file_put_contents($path, $rewritten);
            $files++;
            $references += $count;
        }
    }
}

if ($references === 0) {
    fwrite(STDERR, "Nothing to rewrite in {$copy}: no reference to " . ORIGINAL . " found (already scoped?).\n");
    exit(2);
}

fwrite(STDOUT, sprintf("Scoped %d reference(s) in %d file(s) to %s.\n", $references, $files, $target));
exit(0);

/**
 * @return array{0: string, 1: int}
 */
function rewrite(string $source, string $target): array
{
    $out = '';
    $count = 0;

    foreach (PhpToken::tokenize($source) as $token) {
        $text = $token->text;

        if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
            $leading = str_starts_with($text, '\\') ? '\\' : '';
            $name = ltrim($text, '\\');

            if ($name === ORIGINAL || str_starts_with($name, ORIGINAL . '\\')) {
                $text = $leading . $target . substr($name, strlen(ORIGINAL));
                $count++;
            }
        }

        $out .= $text;
    }

    return [$out, $count];
}
