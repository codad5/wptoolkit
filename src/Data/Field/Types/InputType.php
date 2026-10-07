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
 * A single `<input>`: text, email, url, tel, number, date, color, hidden, password.
 */
final class InputType implements FieldType
{
    /**
     * @param string $inputType The HTML input type.
     * @param string $sanitizeAs A Sanitizer type.
     * @param list<string> $rules Rules every field of this type gets.
     */
    public function __construct(
        private readonly string $inputType,
        private readonly string $sanitizeAs = 'text',
        private readonly array $rules = []
    ) {
    }

    public function sanitize(mixed $value, Field $field): mixed
    {
        if ($this->inputType === 'number') {
            // 0.x stored numbers through absint() and lost negatives; keep the number itself.
            return is_numeric($value) ? $value + 0 : null;
        }

        return Sanitizer::sanitize($value, $this->sanitizeAs);
    }

    public function read(mixed $stored, Field $field): mixed
    {
        if ($this->inputType === 'number' && is_numeric($stored)) {
            return $stored + 0;
        }

        return $stored;
    }

    public function rules(Field $field): array
    {
        return $this->rules;
    }

    public function render(Field $field, string $name, string $id, mixed $value): string
    {
        // A sensitive or password field never echoes what is stored (ADR-0021).
        $shown = $field->isSensitive() || $this->inputType === 'password' ? '' : (is_scalar($value) ? (string) $value : '');

        $attributes = ['type' => $this->inputType, 'id' => $id, 'name' => $name, 'value' => $shown] + $field->htmlAttributes();
        if ($field->isRequired()) {
            $attributes['required'] = true;
        }
        if ($field->isSensitive() && $value !== null && $value !== '') {
            $attributes['placeholder'] = __('Saved — leave blank to keep', 'wptoolkit');
        }

        return '<input' . Html::attributes($attributes) . ' />';
    }
}
