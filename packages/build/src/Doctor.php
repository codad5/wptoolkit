<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * Checks a project before packaging: do its versions agree, does `Requires PHP` match the boot
 * guard, is anything about to leak? Findings are 'error' (the build would fail or ship something
 * wrong) or 'warning' (worth a look).
 */
final class Doctor
{
    /**
     * @return list<array{level: 'error'|'warning', message: string}>
     */
    public static function examine(Project $project): array
    {
        $findings = [];
        $versions = self::versions($project);

        if (count(array_unique($versions)) > 1) {
            $parts = [];
            foreach ($versions as $source => $version) {
                $parts[] = "{$source} = {$version}";
            }
            $findings[] = ['level' => 'error', 'message' => 'Versions disagree: ' . implode(', ', $parts) . '. Pick one source and syncVersionTo() the rest.'];
        }

        $requiresPhp = $project->header('Requires PHP');
        $guardPhp = self::guardPhp($project);
        if ($requiresPhp !== null && $guardPhp !== null && $requiresPhp !== $guardPhp) {
            $findings[] = ['level' => 'error', 'message' => sprintf(
                'The "Requires PHP: %s" header and the guard\'s \'php\' => \'%s\' disagree; WordPress and the guard would check different versions.',
                $requiresPhp,
                $guardPhp
            )];
        }
        if ($project->type === 'plugin' && $guardPhp === null && is_dir($project->root . '/vendor/codad5/wptoolkit')) {
            $findings[] = ['level' => 'warning', 'message' => 'WPToolkit is installed but the main file does not start through bootstrap/guard.php.'];
        }

        foreach (['phpunit', 'phpstan', 'squizlabs', 'mockery'] as $devPackage) {
            if (is_dir($project->root . '/vendor/' . $devPackage)) {
                $findings[] = ['level' => 'warning', 'message' => "vendor/{$devPackage} is installed: package with composer(noDev: true) so it doesn't ship."];
                break;
            }
        }

        if (!is_file($project->root . '/.distignore') && !is_file($project->root . '/wptoolkit.json')) {
            $findings[] = ['level' => 'warning', 'message' => 'No .distignore or wptoolkit.json: only the built-in excludes apply.'];
        }

        return $findings;
    }

    /**
     * @return array<string, string> source => version
     */
    private static function versions(Project $project): array
    {
        $versions = [];
        if (($header = $project->header('Version')) !== null) {
            $versions['header'] = $header;
        }
        foreach (['package.json', 'composer.json'] as $file) {
            $data = is_file($project->root . '/' . $file) ? json_decode((string) file_get_contents($project->root . '/' . $file), true) : null;
            if (is_array($data) && is_string($data['version'] ?? null)) {
                $versions[$file] = $data['version'];
            }
        }
        $readme = $project->root . '/readme.txt';
        if (is_file($readme) && preg_match('/^Stable tag:\s*(\S+)/mi', (string) file_get_contents($readme), $m) === 1 && $m[1] !== 'trunk') {
            $versions['readme.txt'] = $m[1];
        }

        return $versions;
    }

    private static function guardPhp(Project $project): ?string
    {
        $source = (string) file_get_contents($project->headerFile);
        if (!str_contains($source, 'bootstrap/guard.php')) {
            return null;
        }

        return preg_match("/['\"]php['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $source, $m) === 1 ? $m[1] : null;
    }
}
