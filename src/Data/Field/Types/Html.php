<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field\Types;

/**
 * Builds escaped HTML attribute strings for the field types.
 */
final class Html
{
    /**
     * `[ 'id' => 'x', 'required' => true, 'hidden' => false ]` → ` id="x" required`.
     *
     * @param array<string, string|int|float|bool|null> $attributes
     */
    public static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $name = preg_replace('/[^a-z0-9_\-:]/i', '', (string) $name);
            if ($name === '' || $name === null) {
                continue;
            }
            $html .= $value === true ? ' ' . $name : sprintf(' %s="%s"', $name, esc_attr((string) $value));
        }

        return $html;
    }
}
