<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field\Types;

use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldType;
use Codad5\WPToolkit\Support\Validation\Rules;

/**
 * Choosing from the field's options: `select` (optionally multiple) and `radio`. A submitted value
 * that isn't one of the options is rejected — not silently stored.
 */
final class ChoiceType implements FieldType
{
    /**
     * @param 'select'|'radio' $control
     */
    public function __construct(private readonly string $control)
    {
    }

    public function sanitize(mixed $value, Field $field): mixed
    {
        return sanitize_text_field(is_scalar($value) ? (string) $value : '');
    }

    public function read(mixed $stored, Field $field): mixed
    {
        return $stored;
    }

    public function rules(Field $field): array
    {
        $choices = array_map('strval', array_keys($field->choices()));

        return $choices === [] ? [] : [Rules::in($choices)];
    }

    public function render(Field $field, string $name, string $id, mixed $value): string
    {
        $selected = array_map('strval', is_array($value) ? $value : ($value === null ? [] : [$value]));

        if ($this->control === 'radio') {
            $html = '';
            foreach ($field->choices() as $option => $label) {
                $optionId = $id . '-' . sanitize_key((string) $option);
                $html .= sprintf(
                    '<label for="%s"><input%s /> %s</label> ',
                    esc_attr($optionId),
                    Html::attributes([
                        'type' => 'radio',
                        'id' => $optionId,
                        'name' => $name,
                        'value' => (string) $option,
                        'checked' => in_array((string) $option, $selected, true),
                        'required' => $field->isRequired(),
                    ]),
                    esc_html($label)
                );
            }

            return '<fieldset id="' . esc_attr($id) . '">' . $html . '</fieldset>';
        }

        $options = '';
        foreach ($field->choices() as $option => $label) {
            $options .= sprintf(
                '<option%s>%s</option>',
                Html::attributes(['value' => (string) $option, 'selected' => in_array((string) $option, $selected, true)]),
                esc_html($label)
            );
        }

        return sprintf(
            '<select%s>%s</select>',
            Html::attributes([
                'id' => $id,
                'name' => $field->isMultiple() ? $name . '[]' : $name,
                'multiple' => $field->isMultiple(),
                'required' => $field->isRequired(),
            ] + $field->htmlAttributes()),
            $options
        );
    }
}
