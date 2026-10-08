<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\View;

/**
 * Every template receives this as `$e`. Its methods **print** the escaped value, so a template never
 * needs `echo` for data:
 *
 *     <h2><?php $e->html($title); ?></h2>
 *     <a href="<?php $e->url($link); ?>" title="<?php $e->attr($hint); ?>">…</a>
 *
 * That is what makes the PHPCS escaping sniff meaningful for views: a bare `echo $value` is flagged,
 * and there is no unescaped printing method here to reach for. WordPress's own `esc_*()` inside an
 * `echo` work too.
 */
final class Escaper
{
    public function html(mixed $value): void
    {
        echo esc_html(self::text($value));
    }

    public function attr(mixed $value): void
    {
        echo esc_attr(self::text($value));
    }

    public function url(mixed $value): void
    {
        echo esc_url(self::text($value));
    }

    public function textarea(mixed $value): void
    {
        echo esc_textarea(self::text($value));
    }

    /**
     * For inline event-handler attributes. Prefer data attributes plus enqueued scripts.
     */
    public function js(mixed $value): void
    {
        echo esc_js(self::text($value));
    }

    /**
     * HTML limited to what post content allows (`wp_kses_post`).
     */
    public function kses(mixed $value): void
    {
        echo wp_kses_post(self::text($value));
    }

    /**
     * A value for a `data-*` attribute, JSON-encoded then attribute-escaped.
     */
    public function json(mixed $value): void
    {
        echo esc_attr((string) wp_json_encode($value));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
