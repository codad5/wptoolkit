<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\Validation;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Sanitizes a value by its declared type with WordPress's own functions.
 *
 * Types: text (default), textarea, email, url, int, float, bool, key, slug, html (wp_kses_post),
 * array_text (a list of text values), raw (unchanged — use deliberately).
 */
final class Sanitizer
{
    public const TYPES = ['text', 'textarea', 'email', 'url', 'int', 'float', 'bool', 'key', 'slug', 'html', 'array_text', 'raw'];

    public static function sanitize(mixed $value, string $type): mixed
    {
        return match ($type) {
            'text' => sanitize_text_field(self::scalar($value)),
            'textarea' => sanitize_textarea_field(self::scalar($value)),
            'email' => sanitize_email(self::scalar($value)),
            'url' => esc_url_raw(self::scalar($value)),
            'int' => (int) self::scalar($value),
            'float' => (float) self::scalar($value),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'key' => sanitize_key(self::scalar($value)),
            'slug' => sanitize_title(self::scalar($value)),
            'html' => wp_kses_post(self::scalar($value)),
            'array_text' => array_values(array_map(
                static fn ($item): string => sanitize_text_field(self::scalar($item)),
                is_array($value) ? $value : [$value]
            )),
            'raw' => $value,
            default => throw new InvalidConfigException(sprintf('Unknown input type "%s".', $type)),
        };
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
