<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\Validation;

use Closure;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * The built-in rules, and the parser for the `'required|email|max:120'` shorthand.
 *
 * Every rule except `required` passes an absent or empty value: "optional unless required".
 */
final class Rules
{
    public static function required(): Rule
    {
        return self::make(
            static fn (mixed $v): bool => !self::isEmpty($v),
            /* translators: %s: field name */
            static fn (string $f): string => sprintf(__('%s is required.', 'wptoolkit'), $f),
            optional: false
        );
    }

    public static function email(): Rule
    {
        return self::make(
            static fn (mixed $v): bool => is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
            /* translators: %s: field name */
            static fn (string $f): string => sprintf(__('%s must be a valid email address.', 'wptoolkit'), $f)
        );
    }

    public static function url(): Rule
    {
        return self::make(
            static fn (mixed $v): bool => is_string($v) && filter_var($v, FILTER_VALIDATE_URL) !== false
                && preg_match('#^https?://#i', $v) === 1,
            /* translators: %s: field name */
            static fn (string $f): string => sprintf(__('%s must be a valid http(s) URL.', 'wptoolkit'), $f)
        );
    }

    public static function integer(): Rule
    {
        return self::make(
            static fn (mixed $v): bool => is_int($v) || (is_string($v) && preg_match('/^-?\d+$/', $v) === 1),
            /* translators: %s: field name */
            static fn (string $f): string => sprintf(__('%s must be a whole number.', 'wptoolkit'), $f)
        );
    }

    public static function numeric(): Rule
    {
        return self::make(
            static fn (mixed $v): bool => is_numeric($v),
            /* translators: %s: field name */
            static fn (string $f): string => sprintf(__('%s must be a number.', 'wptoolkit'), $f)
        );
    }

    public static function boolean(): Rule
    {
        return self::make(
            static fn (mixed $v): bool => is_bool($v) || in_array($v, [0, 1, '0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], true),
            /* translators: %s: field name */
            static fn (string $f): string => sprintf(__('%s must be true or false.', 'wptoolkit'), $f)
        );
    }

    /**
     * Numbers: at least $min. Strings and arrays: at least $min characters / items.
     */
    public static function min(int|float $min): Rule
    {
        return self::make(
            static fn (mixed $v): bool => self::measure($v) >= $min,
            /* translators: 1: field name, 2: minimum */
            static fn (string $f): string => sprintf(__('%1$s must be at least %2$s.', 'wptoolkit'), $f, (string) $min)
        );
    }

    /**
     * Numbers: at most $max. Strings and arrays: at most $max characters / items.
     */
    public static function max(int|float $max): Rule
    {
        return self::make(
            static fn (mixed $v): bool => self::measure($v) <= $max,
            /* translators: 1: field name, 2: maximum */
            static fn (string $f): string => sprintf(__('%1$s may not be more than %2$s.', 'wptoolkit'), $f, (string) $max)
        );
    }

    /**
     * @param list<string|int> $allowed
     */
    public static function in(array $allowed): Rule
    {
        return self::make(
            static fn (mixed $v): bool => (is_string($v) || is_int($v)) && in_array((string) $v, array_map('strval', $allowed), true),
            /* translators: 1: field name, 2: comma-separated allowed values */
            static fn (string $f): string => sprintf(__('%1$s must be one of: %2$s.', 'wptoolkit'), $f, implode(', ', $allowed))
        );
    }

    public static function regex(string $pattern): Rule
    {
        if (@preg_match($pattern, '') === false) {
            throw new InvalidConfigException(sprintf('Invalid regex rule %s.', $pattern));
        }

        return self::make(
            static fn (mixed $v): bool => is_string($v) && preg_match($pattern, $v) === 1,
            /* translators: %s: field name */
            static fn (string $f): string => sprintf(__('%s has an invalid format.', 'wptoolkit'), $f)
        );
    }

    /**
     * Parse `'required|email|max:120|in:a,b'` into rules. Rule objects pass through untouched.
     *
     * @param string|list<string|Rule> $rules
     * @return list<Rule>
     */
    public static function parse(string|array $rules): array
    {
        $parts = is_string($rules) ? array_filter(explode('|', $rules), static fn ($p) => $p !== '') : $rules;
        $parsed = [];

        foreach ($parts as $part) {
            if ($part instanceof Rule) {
                $parsed[] = $part;
                continue;
            }

            [$name, $argument] = array_pad(explode(':', $part, 2), 2, null);
            $parsed[] = match ($name) {
                'required' => self::required(),
                'email' => self::email(),
                'url' => self::url(),
                'integer', 'int' => self::integer(),
                'numeric' => self::numeric(),
                'boolean', 'bool' => self::boolean(),
                'min' => self::min(self::number($argument, $part)),
                'max' => self::max(self::number($argument, $part)),
                'in' => self::in(explode(',', (string) $argument)),
                'regex' => self::regex((string) $argument),
                default => throw new InvalidConfigException(sprintf('Unknown validation rule "%s".', $part)),
            };
        }

        return $parsed;
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @param Closure(mixed, array<string, mixed>): bool $passes
     * @param Closure(string): string $message
     */
    private static function make(Closure $passes, Closure $message, bool $optional = true): Rule
    {
        return new class ($passes, $message, $optional) implements Rule {
            /**
             * @param Closure(mixed, array<string, mixed>): bool $test
             * @param Closure(string): string $describe
             */
            public function __construct(private readonly Closure $test, private readonly Closure $describe, private readonly bool $optional)
            {
            }

            public function passes(mixed $value, array $data): bool
            {
                if ($this->optional && Rules::isEmpty($value)) {
                    return true;
                }

                return ($this->test)($value, $data);
            }

            public function message(string $field): string
            {
                return ($this->describe)($field);
            }
        };
    }

    private static function measure(mixed $value): float
    {
        return match (true) {
            is_int($value), is_float($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            is_string($value) => (float) mb_strlen($value),
            is_array($value) => (float) count($value),
            default => 0.0,
        };
    }

    private static function number(?string $argument, string $rule): int|float
    {
        if ($argument === null || !is_numeric($argument)) {
            throw new InvalidConfigException(sprintf('Rule "%s" needs a number.', $rule));
        }

        return str_contains($argument, '.') ? (float) $argument : (int) $argument;
    }
}
