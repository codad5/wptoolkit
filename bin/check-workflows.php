<?php

/**
 * Catch the YAML mistakes that silently stop GitHub Actions from running at all — a broken
 * workflow file can't report its own failure. Flags unquoted `run:` values containing ": " or
 * " #", which YAML reads as a mapping or a comment.
 *
 * Usage: php bin/check-workflows.php
 */

declare(strict_types=1);

$problems = 0;
foreach (glob(dirname(__DIR__) . '/.github/workflows/*.yml') ?: [] as $file) {
    foreach (file($file) ?: [] as $number => $line) {
        if (preg_match('/^\s*-?\s*run:\s*(?![|>"\'])(.+)$/', $line, $m) !== 1) {
            continue;
        }
        if (str_contains($m[1], ': ') || str_contains($m[1], ' #')) {
            $problems++;
            fwrite(STDERR, sprintf(
                "%s:%d: unquoted run: value contains \": \" or \" #\" — use a block scalar (run: |)\n",
                basename($file),
                $number + 1
            ));
        }
    }
}

fwrite(STDOUT, $problems === 0 ? "Workflows OK.\n" : "{$problems} workflow problem(s).\n");
exit($problems === 0 ? 0 : 1);
