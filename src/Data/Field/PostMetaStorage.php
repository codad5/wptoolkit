<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field;

/**
 * Reads and writes one field's post meta in 0.x's shapes (ADR-0016), for MetaBox and
 * PostTypeRepository alike:
 *
 * - a single value: one row;
 * - a multiple media field: one row per attachment ID;
 * - any other multiple field: one row holding a serialized array.
 *
 * Reads accept either multiple shape, so data written by any version reads the same.
 * Values are stored values (already sanitized); writes slash them because the meta API unslashes.
 */
final class PostMetaStorage
{
    /**
     * @return mixed Null when nothing is stored; a list for multiple fields; otherwise the row.
     */
    public static function read(int $postId, string $key, Field $field): mixed
    {
        $rows = get_post_meta($postId, $key, false);
        $rows = is_array($rows) ? array_values($rows) : [];
        if ($rows === []) {
            return null;
        }

        if (!$field->isMultiple()) {
            return $rows[0];
        }

        return count($rows) === 1 && is_array($rows[0]) ? array_values($rows[0]) : $rows;
    }

    public static function write(int $postId, string $key, Field $field, mixed $stored): void
    {
        if ($stored === null || $stored === []) {
            delete_post_meta($postId, $key);
            return;
        }

        if ($field->storesOneRowPerValue()) {
            delete_post_meta($postId, $key);
            foreach (is_array($stored) ? $stored : [$stored] as $value) {
                add_post_meta($postId, $key, wp_slash($value));
            }
            return;
        }

        update_post_meta($postId, $key, wp_slash($field->isMultiple() ? array_values((array) $stored) : $stored));
    }
}
