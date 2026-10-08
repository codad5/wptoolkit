<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Checks a version against a Composer-style constraint, without depending on composer/semver
 * (ADR-0012). Supports what `requires_toolkit` needs:
 *
 * - exact `1.2.3`, comparisons `>=1.2`, `>1.2`, `<=2.0`, `<2.0`, `!=1.3.0`
 * - caret `^1.2` (>=1.2.0 <2.0.0; `^0.3` is >=0.3.0 <0.4.0) and tilde `~1.2` (>=1.2.0 <2.0.0),
 *   `~1.2.3` (>=1.2.3 <1.3.0)
 * - AND with spaces or commas, OR with `||`
 *
 * Pre-release suffixes compare with PHP's version_compare(): `1.0.0-dev` < `1.0.0`.
 */
final class VersionConstraint
{
    public static function satisfies(string $version, string $constraint): bool
    {
        foreach (explode('||', $constraint) as $alternative) {
            $parts = preg_split('/[\s,]+/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($parts === []) {
                throw new InvalidConfigException(sprintf('Empty version constraint in "%s".', $constraint));
            }

            $all = true;
            foreach ($parts as $part) {
                if (!self::satisfiesOne($version, $part)) {
                    $all = false;
                    break;
                }
            }

            if ($all) {
                return true;
            }
        }

        return false;
    }

    private static function satisfiesOne(string $version, string $part): bool
    {
        if (preg_match('/^(\^|~|>=|<=|>|<|!=|==|=)?\s*v?(\d+(?:\.\d+){0,2})([-+][\w.\-]+)?$/', $part, $m) !== 1) {
            throw new InvalidConfigException(sprintf('Unsupported version constraint "%s".', $part));
        }

        $operator = $m[1] === '' ? '=' : $m[1];
        $bound = $m[2] . ($m[3] ?? '');
        $segments = array_map('intval', explode('.', $m[2]));

        return match ($operator) {
            '^' => self::atLeast($version, $bound) && self::below($version, self::caretCeiling($segments)),
            '~' => self::atLeast($version, $bound) && self::below($version, self::tildeCeiling($segments)),
            '>=' => self::atLeast($version, $bound),
            '>' => version_compare(self::normalize($version), self::normalize($bound), '>'),
            '<=' => version_compare(self::normalize($version), self::normalize($bound), '<='),
            '<' => self::below($version, $bound),
            '!=' => version_compare(self::normalize($version), self::normalize($bound), '!='),
            default => version_compare(self::normalize($version), self::normalize($bound), '=='),
        };
    }

    /**
     * @param list<int> $s
     */
    private static function caretCeiling(array $s): string
    {
        [$major, $minor, $patch] = $s + [0, 0, 0];

        if ($major > 0 || count($s) === 1) {
            return ($major + 1) . '.0.0';
        }
        if ($minor > 0 || count($s) === 2) {
            return '0.' . ($minor + 1) . '.0';
        }

        return '0.0.' . ($patch + 1);
    }

    /**
     * @param list<int> $s
     */
    private static function tildeCeiling(array $s): string
    {
        [$major, $minor] = $s + [0, 0];

        return count($s) >= 3 ? $major . '.' . ($minor + 1) . '.0' : ($major + 1) . '.0.0';
    }

    private static function atLeast(string $version, string $bound): bool
    {
        // A dev/pre-release of the bound itself (1.0.0-dev) satisfies >=1.0.0 during development.
        $base = (string) preg_replace('/[-+].*$/', '', $version);

        return version_compare(self::normalize($version), self::normalize($bound), '>=')
            || version_compare(self::normalize($base), self::normalize($bound), '==');
    }

    private static function below(string $version, string $ceiling): bool
    {
        $base = (string) preg_replace('/[-+].*$/', '', $version);

        return version_compare(self::normalize($base), self::normalize($ceiling), '<');
    }

    /**
     * Pad to three segments so 1.2 and 1.2.0 compare equal.
     */
    private static function normalize(string $version): string
    {
        $suffix = '';
        if (preg_match('/^(\d+(?:\.\d+)*)([-+].*)?$/', ltrim($version, 'v'), $m) === 1) {
            $segments = explode('.', $m[1]);
            $suffix = $m[2] ?? '';
            return implode('.', array_pad($segments, 3, '0')) . $suffix;
        }

        return $version . $suffix;
    }
}
