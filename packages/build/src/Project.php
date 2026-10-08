<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

/**
 * What is being built: a plugin (main file found by its `Plugin Name:` header), a theme
 * (`style.css` with `Theme Name:`) or a library (a named slug and the file holding its version).
 * For plugins and themes the slug is the directory name, as WordPress uses it.
 */
final class Project
{
    /**
     * @param 'plugin'|'theme'|'library' $type
     */
    private function __construct(
        public readonly string $root,
        public readonly string $type,
        public readonly string $slug,
        public readonly string $headerFile
    ) {
    }

    public static function plugin(string $root): self
    {
        $root = self::normalizeRoot($root);
        foreach (glob($root . '/*.php') ?: [] as $file) {
            if (self::readHeader($file, 'Plugin Name') !== null) {
                return new self($root, 'plugin', basename($root), $file);
            }
        }

        throw new BuildException(sprintf('No plugin main file (a PHP file with a "Plugin Name:" header) in %s.', $root));
    }

    /**
     * A library such as WPToolkit itself: not installed by WordPress, so the slug is given and
     * `$versionFile` (relative to the root) is where Version::fromConstant() looks.
     */
    public static function library(string $root, string $slug, string $versionFile): self
    {
        $root = self::normalizeRoot($root);
        if (!is_file($root . '/' . $versionFile)) {
            throw new BuildException(sprintf('Version file %s not found in %s.', $versionFile, $root));
        }

        return new self($root, 'library', $slug, $root . '/' . $versionFile);
    }

    public static function theme(string $root): self
    {
        $root = self::normalizeRoot($root);
        $style = $root . '/style.css';
        if (self::readHeader($style, 'Theme Name') === null) {
            throw new BuildException(sprintf('No style.css with a "Theme Name:" header in %s.', $root));
        }

        return new self($root, 'theme', basename($root), $style);
    }

    /**
     * A header from the main plugin file or style.css, e.g. `Version`, `Requires PHP`, `Text Domain`.
     */
    public function header(string $name): ?string
    {
        return self::readHeader($this->headerFile, $name);
    }

    public function relativeHeaderFile(): string
    {
        return substr($this->headerFile, strlen($this->root) + 1);
    }

    /**
     * Like WordPress's get_file_data(): look in the first 8 KB for `Name: value`.
     */
    public static function readHeader(string $file, string $name): ?string
    {
        if (!is_readable($file)) {
            return null;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }
        $head = (string) fread($handle, 8192);
        fclose($handle);

        if (preg_match('/^[ \t\/*#@]*' . preg_quote($name, '/') . ':(.*)$/mi', $head, $match) !== 1) {
            return null;
        }

        $value = trim((string) preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));

        return $value === '' ? null : $value;
    }

    private static function normalizeRoot(string $root): string
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new BuildException(sprintf('Project directory %s does not exist.', $root));
        }

        return rtrim(str_replace('\\', '/', $real), '/');
    }
}
