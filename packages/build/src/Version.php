<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;

/**
 * Built-in version sources:
 *
 *     Version::fromPackageJson() · fromComposerJson() · fromHeader() · fromGitTag()
 *     Version::fromConstant('MY_PLUGIN_VERSION') · fixed('1.2.3')
 */
final class Version
{
    public static function fromPackageJson(string $file = 'package.json'): VersionSource
    {
        return self::source("{$file} \"version\"", static fn (Project $p) => self::jsonVersion($p->root . '/' . $file, $file));
    }

    public static function fromComposerJson(string $file = 'composer.json'): VersionSource
    {
        return self::source("{$file} \"version\"", static fn (Project $p) => self::jsonVersion($p->root . '/' . $file, $file));
    }

    /**
     * The `Version:` header of the main plugin file or style.css.
     */
    public static function fromHeader(): VersionSource
    {
        return self::source('the Version header', static fn (Project $p) => $p->header('Version')
            ?? throw new BuildException(sprintf('%s has no "Version:" header.', $p->relativeHeaderFile())));
    }

    /**
     * The most recent git tag (a leading "v" is dropped).
     */
    public static function fromGitTag(): VersionSource
    {
        return self::source('the latest git tag', static function (Project $p, CommandRunner $runner): string {
            $result = $runner->run('git describe --tags --abbrev=0', $p->root);
            $tag = trim($result['output']);
            if ($result['exitCode'] !== 0 || $tag === '') {
                throw new BuildException('No git tag found (git describe --tags --abbrev=0 failed).');
            }

            return ltrim($tag, 'vV');
        });
    }

    /**
     * A PHP constant in the main file: `define('NAME', '1.2.3')` or `const NAME = '1.2.3'`.
     */
    public static function fromConstant(string $name): VersionSource
    {
        return self::source("the {$name} constant", static function (Project $p) use ($name): string {
            $source = (string) file_get_contents($p->headerFile);
            $quoted = preg_quote($name, '/');
            if (
                preg_match("/define\(\s*['\"]{$quoted}['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $source, $m) === 1
                || preg_match("/const\s+{$quoted}\s*=\s*['\"]([^'\"]+)['\"]/", $source, $m) === 1
            ) {
                return $m[1];
            }

            throw new BuildException(sprintf('Constant %s not found in %s.', $name, $p->relativeHeaderFile()));
        });
    }

    public static function fixed(string $version): VersionSource
    {
        return self::source('a fixed value', static fn () => $version);
    }

    /**
     * @param Closure(Project, CommandRunner): string $resolve
     */
    private static function source(string $description, Closure $resolve): VersionSource
    {
        return new class ($description, $resolve) implements VersionSource {
            /**
             * @param Closure(Project, CommandRunner): string $resolve
             */
            public function __construct(private readonly string $description, private readonly Closure $resolve)
            {
            }

            public function resolve(Project $project, CommandRunner $runner): string
            {
                $version = trim(($this->resolve)($project, $runner));
                if (preg_match('/^\d+(\.\d+){0,3}([-+][\w.\-]+)?$/', $version) !== 1) {
                    throw new BuildException(sprintf('"%s" from %s is not a version number.', $version, $this->description));
                }

                return $version;
            }

            public function describe(): string
            {
                return $this->description;
            }
        };
    }

    private static function jsonVersion(string $path, string $label): string
    {
        $data = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        $version = is_array($data) ? ($data['version'] ?? null) : null;

        return is_string($version) ? $version : throw new BuildException(sprintf('%s has no "version".', $label));
    }
}
