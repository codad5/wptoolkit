<?php

/**
 * Release notes from Conventional Commits (ADR-0011): commits since the previous tag, grouped by type,
 * breaking changes first.
 *
 * Usage: php bin/changelog.php [FROM] [TO]
 *   FROM  defaults to the previous tag before TO (all history if there is none)
 *   TO    defaults to HEAD
 *
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

const GROUPS = [
    'feat' => 'Features',
    'fix' => 'Fixes',
    'perf' => 'Performance',
    'refactor' => 'Refactoring',
    'docs' => 'Documentation',
];

/**
 * @param list<array{subject: string, body: string, hash: string}> $commits
 */
function render_changelog(array $commits): string
{
    $breaking = [];
    $groups = array_fill_keys(array_keys(GROUPS), []);

    foreach ($commits as $commit) {
        if (preg_match('/^(\w+)(\(([^)]+)\))?(!)?: (.+)$/', $commit['subject'], $m) !== 1) {
            continue; // not a Conventional Commit: leave it out of the notes
        }
        [$type, $scope, $bang, $summary] = [$m[1], $m[3], $m[4], $m[5]];
        $line = '- ' . ($scope !== '' ? "**{$scope}:** " : '') . $summary . ' (' . substr($commit['hash'], 0, 7) . ')';

        // Read the footer first: `||` would skip it whenever the subject already has a `!`.
        $footer = preg_match('/^BREAKING CHANGE: (.+)/ms', $commit['body'], $b) === 1;
        if ($bang === '!' || $footer) {
            $note = $footer ? trim((string) preg_replace('/\s+/', ' ', explode("\n\n", $b[1])[0])) : $summary;
            $breaking[] = '- ' . ($scope !== '' ? "**{$scope}:** " : '') . $note;
        }
        if (isset($groups[$type])) {
            $groups[$type][] = $line;
        }
    }

    $out = '';
    if ($breaking !== []) {
        $out .= "### Breaking changes\n\n" . implode("\n", $breaking) . "\n\n";
    }
    foreach ($groups as $type => $lines) {
        if ($lines !== []) {
            $out .= '### ' . GROUPS[$type] . "\n\n" . implode("\n", $lines) . "\n\n";
        }
    }

    return $out === '' ? "No user-facing changes.\n" : rtrim($out) . "\n";
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $to = $argv[2] ?? 'HEAD';
    $from = ($argv[1] ?? '') !== ''
        ? $argv[1]
        : trim((string) shell_exec('git describe --tags --abbrev=0 ' . escapeshellarg($to . '^') . ' 2>/dev/null'));
    $range = $from === '' ? $to : $from . '..' . $to;

    $raw = (string) shell_exec('git log --no-merges --format=%H%x1f%s%x1f%b%x1e ' . escapeshellarg($range));
    $commits = [];
    foreach (array_filter(array_map('trim', explode("\x1e", $raw))) as $entry) {
        [$hash, $subject, $body] = array_pad(explode("\x1f", $entry), 3, '');
        $commits[] = ['hash' => $hash, 'subject' => $subject, 'body' => $body];
    }

    echo render_changelog($commits);
}
