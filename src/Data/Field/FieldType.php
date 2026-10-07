<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field;

use Codad5\WPToolkit\Support\Validation\Rule;

/**
 * How one kind of field behaves (Strategy). Register your own with `FieldTypes::register()` —
 * no change to the library needed.
 */
interface FieldType
{
    /**
     * Turn one submitted value into what is stored. For multiple fields this is called per value.
     */
    public function sanitize(mixed $value, Field $field): mixed;

    /**
     * Turn one stored value back into what code reads (e.g. "on" → true for a checkbox).
     */
    public function read(mixed $stored, Field $field): mixed;

    /**
     * Rules every field of this type gets (e.g. `email` for an email field).
     *
     * @return list<string|Rule>
     */
    public function rules(Field $field): array;

    /**
     * Escaped HTML for the input. `$name` is the form field name, `$id` the element id.
     */
    public function render(Field $field, string $name, string $id, mixed $value): string;
}
