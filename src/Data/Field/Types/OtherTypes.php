<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field\Types;

use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldType;
use Codad5\WPToolkit\Support\Validation\Sanitizer;

/**
 * Factories for the remaining built-in types: textarea, checkbox, media, wysiwyg.
 */
final class OtherTypes
{
    public static function textarea(): FieldType
    {
        return new class implements FieldType {
            public function sanitize(mixed $value, Field $field): mixed
            {
                return Sanitizer::sanitize($value, 'textarea');
            }

            public function read(mixed $stored, Field $field): mixed
            {
                return $stored;
            }

            public function rules(Field $field): array
            {
                return [];
            }

            public function render(Field $field, string $name, string $id, mixed $value): string
            {
                return sprintf(
                    '<textarea%s>%s</textarea>',
                    Html::attributes(['id' => $id, 'name' => $name, 'rows' => 4, 'required' => $field->isRequired()] + $field->htmlAttributes()),
                    esc_textarea(is_scalar($value) ? (string) $value : '')
                );
            }
        };
    }

    /**
     * A single on/off checkbox. Stores "1" or "0"; reads 0.x's stored "on"/"yes"/"true" as true too.
     */
    public static function checkbox(): FieldType
    {
        return new class implements FieldType {
            public function sanitize(mixed $value, Field $field): mixed
            {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            public function read(mixed $stored, Field $field): mixed
            {
                return in_array(is_scalar($stored) ? strtolower((string) $stored) : '', ['1', 'on', 'yes', 'true'], true);
            }

            public function rules(Field $field): array
            {
                return [];
            }

            public function render(Field $field, string $name, string $id, mixed $value): string
            {
                // The hidden input submits "0" when the box is unticked, so unticking saves.
                return sprintf('<input type="hidden" name="%s" value="0" />', esc_attr($name))
                    . '<input' . Html::attributes([
                        'type' => 'checkbox',
                        'id' => $id,
                        'name' => $name,
                        'value' => '1',
                        'checked' => $this->read($value, $field),
                    ] + $field->htmlAttributes()) . ' />';
            }
        };
    }

    /**
     * Attachments from the media library, stored as attachment IDs — one meta row per ID when
     * multiple, exactly as 0.x's `wp_media` type stored them (ADR-0016).
     */
    public static function media(): FieldType
    {
        return new class implements FieldType {
            public function sanitize(mixed $value, Field $field): mixed
            {
                $id = absint(is_scalar($value) ? $value : 0);

                return $id > 0 ? $id : null;
            }

            public function read(mixed $stored, Field $field): mixed
            {
                return is_numeric($stored) ? (int) $stored : null;
            }

            public function rules(Field $field): array
            {
                return ['integer'];
            }

            public function render(Field $field, string $name, string $id, mixed $value): string
            {
                $ids = array_filter(array_map('absint', is_array($value) ? $value : [$value]));

                // The admin script (Phase 5 assets) opens the media modal for [data-wptoolkit-media].
                return sprintf(
                    '<div%s><input type="hidden" name="%s" value="%s" /><button type="button" class="button">%s</button></div>',
                    Html::attributes([
                        'id' => $id,
                        'data-wptoolkit-media' => true,
                        'data-multiple' => $field->isMultiple() ? '1' : '0',
                    ]),
                    esc_attr($field->isMultiple() ? $name . '[]' : $name),
                    esc_attr(implode(',', $ids)),
                    esc_html__('Choose media', 'wptoolkit')
                );
            }
        };
    }

    /**
     * Rich text with WordPress's editor. Stored through wp_kses_post().
     */
    public static function wysiwyg(): FieldType
    {
        return new class implements FieldType {
            public function sanitize(mixed $value, Field $field): mixed
            {
                return Sanitizer::sanitize($value, 'html');
            }

            public function read(mixed $stored, Field $field): mixed
            {
                return $stored;
            }

            public function rules(Field $field): array
            {
                return [];
            }

            public function render(Field $field, string $name, string $id, mixed $value): string
            {
                ob_start();
                wp_editor(is_string($value) ? $value : '', $id, ['textarea_name' => $name, 'textarea_rows' => 8]);

                return (string) ob_get_clean();
            }
        };
    }
}
