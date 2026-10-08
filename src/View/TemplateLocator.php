<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\View;

use Codad5\WPToolkit\Exceptions\ViewException;

/**
 * Finds the file for a view name, in order:
 *
 * 1. the active (child, then parent) theme: `{theme}/{folder}/{view}` and `{view}.php` — 0.x's
 *    override paths, so existing theme overrides keep working;
 * 2. the plugin's view directories, in the order added;
 * 3. `{dir}/{view}/index.php`.
 *
 * View names are relative (`admin/settings`, `emails/welcome.php`); `..` and absolute paths are
 * refused, and a match must resolve inside the directory it was found in.
 */
final class TemplateLocator
{
    /** @var list<string> */
    private array $paths = [];

    /**
     * @param list<string> $paths Plugin view directories, highest priority first.
     * @param string|null $themeFolder The folder themes override in (0.x: the plugin prefix); null disables overrides.
     */
    public function __construct(array $paths, private readonly ?string $themeFolder = null)
    {
        foreach ($paths as $path) {
            $this->addPath($path);
        }
    }

    public function addPath(string $path, bool $first = false): void
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($first) {
            array_unshift($this->paths, $path);
        } else {
            $this->paths[] = $path;
        }
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @throws ViewException For an invalid name.
     */
    public function locate(string $view): ?string
    {
        $view = $this->normalize($view);
        $hasExtension = pathinfo($view, PATHINFO_EXTENSION) !== '';

        if ($this->themeFolder !== null && function_exists('locate_template')) {
            $candidates = $hasExtension ? ["{$this->themeFolder}/{$view}"] : ["{$this->themeFolder}/{$view}", "{$this->themeFolder}/{$view}.php"];
            $found = locate_template($candidates);
            if ($found !== '' && is_file($found)) {
                return $found;
            }
        }

        foreach ($this->paths as $base) {
            $found = $this->inDirectory($base, $view, $hasExtension);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Where locate() looks, for error messages.
     *
     * @return list<string>
     */
    public function searched(string $view): array
    {
        $searched = $this->themeFolder !== null ? ["(theme)/{$this->themeFolder}/{$view}"] : [];
        foreach ($this->paths as $base) {
            $searched[] = "{$base}/{$view}";
        }

        return $searched;
    }

    private function inDirectory(string $base, string $view, bool $hasExtension): ?string
    {
        $root = realpath($base);
        if ($root === false) {
            return null;
        }

        $candidates = $hasExtension ? ["{$base}/{$view}"] : ["{$base}/{$view}.php", "{$base}/{$view}/index.php"];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_file($real) && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        return null;
    }

    private function normalize(string $view): string
    {
        $view = trim(str_replace('\\', '/', $view), '/');
        if ($view === '' || preg_match('#^[A-Za-z0-9_\-./]+$#', $view) !== 1 || in_array('..', explode('/', $view), true)) {
            throw ViewException::invalidName($view);
        }

        return $view;
    }
}
