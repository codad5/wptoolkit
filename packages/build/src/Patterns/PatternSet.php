<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Patterns;

/**
 * Gitignore-style path matching for include/exclude rules.
 *
 * - `name` (no slash) matches that file or directory name at any depth;
 * - a pattern containing `/` (or starting with one) is anchored to the project root;
 * - a trailing `/` matches directories only (and everything inside them);
 * - `*` matches within one path segment, `**` across segments, `?` one character;
 * - `!pattern` re-includes; the last matching rule wins.
 *
 * A path also matches when any of its parent directories matches, so `node_modules` excludes
 * `a/node_modules/x.js`.
 */
final class PatternSet
{
    /** @var list<array{regex: string, negate: bool, directoryOnly: bool}> */
    private array $rules = [];

    /**
     * @param iterable<string> $patterns
     */
    public function __construct(iterable $patterns = [])
    {
        $this->add(...$patterns);
    }

    public function add(string ...$patterns): self
    {
        foreach ($patterns as $pattern) {
            $rule = self::compile($pattern);
            if ($rule !== null) {
                $this->rules[] = $rule;
            }
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * @param string $path Relative to the project root, `/`-separated.
     */
    public function matches(string $path, bool $isDirectory = false): bool
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $segments = explode('/', $path);
        $matched = false;

        // Check every ancestor directory, then the path itself.
        for ($depth = 1; $depth <= count($segments); $depth++) {
            $candidate = implode('/', array_slice($segments, 0, $depth));
            $candidateIsDirectory = $depth < count($segments) || $isDirectory;

            foreach ($this->rules as $rule) {
                if ($rule['directoryOnly'] && !$candidateIsDirectory) {
                    continue;
                }
                if (preg_match($rule['regex'], $candidate) === 1) {
                    $matched = !$rule['negate'];
                }
            }

            if ($matched && $depth < count($segments)) {
                return true; // an excluded directory excludes everything inside it
            }
        }

        return $matched;
    }

    /**
     * @return array{regex: string, negate: bool, directoryOnly: bool}|null
     */
    private static function compile(string $pattern): ?array
    {
        $pattern = trim($pattern);
        if ($pattern === '' || str_starts_with($pattern, '#')) {
            return null;
        }

        $negate = str_starts_with($pattern, '!');
        if ($negate) {
            $pattern = substr($pattern, 1);
        }

        $pattern = str_replace('\\', '/', $pattern);
        if (str_starts_with($pattern, './')) {
            $pattern = substr($pattern, 2);
        }

        $directoryOnly = str_ends_with($pattern, '/');
        // Anchored when it starts with "/" or has a "/" before its end — decided before trimming.
        $anchored = str_contains(rtrim($pattern, '/'), '/');
        $pattern = trim($pattern, '/');
        if ($pattern === '') {
            return null;
        }

        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '*' && ($pattern[$i + 1] ?? '') === '*') {
                $i++;
                if (($pattern[$i + 1] ?? '') === '/') {
                    $i++;
                    $regex .= '(?:.*/)?'; // "**/" — zero or more directories
                } else {
                    $regex .= '.*';
                }
            } elseif ($char === '*') {
                $regex .= '[^/]*';
            } elseif ($char === '?') {
                $regex .= '[^/]';
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        return [
            'regex' => '#^' . ($anchored ? '' : '(?:.*/)?') . $regex . '$#',
            'negate' => $negate,
            'directoryOnly' => $directoryOnly,
        ];
    }
}
