<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use PhpToken;

/**
 * Renames references to WPToolkit in PHP source, token by token — the same rule as the library's
 * own bin/scope.php, applied to the code that *uses* the library. Strings and comments are left
 * alone, and so is the packager's namespace (`…\Build`), which scoping never renames.
 */
final class NamespaceRewriter
{
    public const ORIGINAL = 'Codad5\\WPToolkit';

    private const KEPT = self::ORIGINAL . '\\Build';

    public function __construct(private readonly string $target)
    {
    }

    /**
     * @return array{0: string, 1: int} The rewritten source and how many references changed.
     */
    public function rewrite(string $source): array
    {
        $out = '';
        $count = 0;

        foreach (PhpToken::tokenize($source) as $token) {
            $text = $token->text;
            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && $this->isToolkitName(ltrim($text, '\\'))) {
                $leading = str_starts_with($text, '\\') ? '\\' : '';
                $text = $leading . $this->target . substr(ltrim($text, '\\'), strlen(self::ORIGINAL));
                $count++;
            }
            $out .= $text;
        }

        return [$out, $count];
    }

    /**
     * How many references to the unscoped library a source still has.
     */
    public function remaining(string $source): int
    {
        $count = 0;
        foreach (PhpToken::tokenize($source) as $token) {
            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && $this->isToolkitName(ltrim($token->text, '\\'))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Whether a string literal in the source names the unscoped library. Scoping can't rewrite
     * strings safely, so callers report these for a human to fix (use `Foo::class` instead).
     */
    public function namesToolkitInAString(string $source): bool
    {
        foreach (PhpToken::tokenize($source) as $token) {
            if (!$token->is([T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE])) {
                continue;
            }
            $text = str_replace('\\\\', '\\', $token->text);
            if (str_contains($text, self::ORIGINAL . '\\') && !str_contains($text, self::KEPT)) {
                return true;
            }
        }

        return false;
    }

    private function isToolkitName(string $name): bool
    {
        if ($name === self::KEPT || str_starts_with($name, self::KEPT . '\\')) {
            return false;
        }

        return $name === self::ORIGINAL || str_starts_with($name, self::ORIGINAL . '\\');
    }
}
