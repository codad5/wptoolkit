<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * Builds a Build from `wptoolkit.json` — the zero-code path to the same pipeline as build.php.
 *
 *     {
 *       "type": "plugin",
 *       "version": "package.json",            // package.json | composer.json | header | git-tag | constant:NAME
 *       "syncVersionTo": ["header", "readme.txt"],
 *       "run": ["npm ci", "npm run build"],
 *       "composer": "no-dev",                  // no-dev | dev | false
 *       "scope": { "namespace": "MyPlugin\\WPToolkit", "path": "lib/wptoolkit" },
 *       "pot": true,
 *       "exclude": ["docs", ".distignore"],    // a name ending in ignore/attributes is read as a pattern file
 *       "include": ["assets/dist"],
 *       "verify": { "forbid": ["*.sql"], "maxSizeMb": 5 },
 *       "zip": "dist/{slug}-{version}.zip"
 *     }
 *
 * Every key is optional.
 */
final class JsonConfig
{
    /**
     * @return array{0: Build, 1: string} The build and the zip target.
     */
    public static function load(string $projectDirectory, string $file = 'wptoolkit.json'): array
    {
        $path = rtrim($projectDirectory, '/\\') . '/' . $file;
        $config = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (!is_array($decoded)) {
                throw new BuildException(sprintf('%s is not valid JSON: %s', $file, json_last_error_msg()));
            }
            $config = $decoded;
        }

        $type = $config['type'] ?? (is_file($projectDirectory . '/style.css') ? 'theme' : 'plugin');
        $build = $type === 'theme' ? Build::theme($projectDirectory) : Build::plugin($projectDirectory);

        if (isset($config['version'])) {
            $build->version(self::versionSource((string) $config['version']));
        }
        if (!empty($config['run'])) {
            $build->run(...self::strings($config['run'], 'run'));
        }
        if (($config['composer'] ?? false) !== false) {
            $build->composer(noDev: $config['composer'] !== 'dev');
        }
        if (isset($config['scope']['namespace'])) {
            $build->scope((string) $config['scope']['namespace'], isset($config['scope']['path']) ? (string) $config['scope']['path'] : null);
        }
        if (!empty($config['pot'])) {
            $build->makePot(is_string($config['pot']) ? $config['pot'] : null);
        }
        if (!empty($config['syncVersionTo'])) {
            $build->syncVersionTo(...self::strings($config['syncVersionTo'], 'syncVersionTo'));
        }
        foreach (self::strings($config['exclude'] ?? [], 'exclude') as $pattern) {
            $build->exclude(self::patternOrFile($pattern));
        }
        foreach (self::strings($config['include'] ?? [], 'include') as $pattern) {
            $build->include(self::patternOrFile($pattern));
        }
        if (isset($config['verify']) && is_array($config['verify'])) {
            $build->verify(
                self::strings($config['verify']['forbid'] ?? [], 'verify.forbid'),
                isset($config['verify']['maxSizeMb']) ? (int) $config['verify']['maxSizeMb'] : null
            );
        }

        return [$build, (string) ($config['zip'] ?? 'dist/{slug}-{version}.zip')];
    }

    private static function versionSource(string $name): VersionSource
    {
        return match (true) {
            $name === 'package.json' => Version::fromPackageJson(),
            $name === 'composer.json' => Version::fromComposerJson(),
            $name === 'header' => Version::fromHeader(),
            $name === 'git-tag' => Version::fromGitTag(),
            str_starts_with($name, 'constant:') => Version::fromConstant(substr($name, 9)),
            default => throw new BuildException(sprintf(
                'Unknown "version" source "%s". Use package.json, composer.json, header, git-tag or constant:NAME.',
                $name
            )),
        };
    }

    /**
     * `.distignore`, `.gitignore` and `.gitattributes` are read as pattern files; anything else is a glob.
     */
    private static function patternOrFile(string $pattern): string|Patterns\PatternSource
    {
        return match (basename($pattern)) {
            '.distignore' => Patterns::fromDistignore($pattern),
            '.gitignore' => Patterns::fromGitignore($pattern),
            '.gitattributes' => Patterns::fromGitattributesExportIgnore($pattern),
            default => $pattern,
        };
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            throw new BuildException(sprintf('"%s" must be a list of strings.', $key));
        }

        return array_values(array_map('strval', $value));
    }
}
