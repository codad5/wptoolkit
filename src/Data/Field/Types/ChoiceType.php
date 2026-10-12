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
    /** A list this short is clearer as plain checkboxes than as a search box. */
    private const MAX_PLAIN_CHECKBOXES = 6;

    /**
     * @param 'select'|'radio' $control
     */
    public function __construct(private readonly string $control, private readonly ?ChoicePickerAssets $assets = null)
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
        $selected = array_values(array_map('strval', is_array($value) ? $value : ($value === null ? [] : [$value])));

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

        if ($field->isMultiple() && $this->displayOf($field) !== 'native') {
            return $this->renderCheckboxes($field, $name, $id, $selected);
        }

        $options = '';
        foreach ($field->choices() as $option => $label) {
            $options .= sprintf(
                '<option%s>%s</option>',
                Html::attributes(['value' => (string) $option, 'selected' => in_array((string) $option, $selected, true)]),
                esc_html($label)
            );
        }

        return ($field->isMultiple() ? self::emptyMarker($name) : '') . sprintf(
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

    /**
     * Sent first so that ticking nothing still submits the field. Without it the key is missing, the
     * save treats the field as "not on this form", and a selection could never be cleared.
     */
    private static function emptyMarker(string $name): string
    {
        return sprintf('<input type="hidden" name="%s[]" value="" />', esc_attr($name));
    }

    private function displayOf(Field $field): string
    {
        $display = $field->setting('display');
        if (is_string($display)) {
            return $display;
        }

        return count($field->choices()) <= self::MAX_PLAIN_CHECKBOXES ? 'checkboxes' : 'tokens';
    }

    /**
     * A multiple choice as real checkboxes. With 'tokens' a script (ChoicePickerAssets) turns the list
     * into chips with search; the checkboxes remain the source of truth either way.
     *
     * @param list<string> $selected
     */
    private function renderCheckboxes(Field $field, string $name, string $id, array $selected): string
    {
        $mode = $this->displayOf($field);
        $attributes = $field->htmlAttributes();
        $class = trim('wptoolkit-picker ' . (isset($attributes['class']) ? (string) $attributes['class'] : ''));
        unset($attributes['class']);

        $boxes = '';
        foreach ($field->choices() as $option => $label) {
            $boxId = $id . '-' . sanitize_key((string) $option);
            $boxes .= sprintf(
                '<label for="%s"><input%s /> <span>%s</span></label>',
                esc_attr($boxId),
                Html::attributes([
                    'type' => 'checkbox',
                    'id' => $boxId,
                    'name' => $name . '[]',
                    'value' => (string) $option,
                    'checked' => in_array((string) $option, $selected, true),
                ]),
                esc_html($label)
            );
        }

        $labels = [];
        if ($mode === 'tokens') {
            $this->assets?->queue();
            $labels = [
                'data-label-search' => __('Search…', 'wptoolkit'),
                /* translators: %s: name of a selected item */
                'data-label-remove' => __('Remove %s', 'wptoolkit'),
                'data-label-none' => __('No matches', 'wptoolkit'),
            ];
        }

        return sprintf(
            '<fieldset%s><legend class="screen-reader-text">%s</legend>%s%s</fieldset>',
            Html::attributes(['id' => $id, 'class' => $class, 'data-wptoolkit-picker' => $mode] + $labels + $attributes),
            esc_html($field->labelText()),
            self::emptyMarker($name),
            $boxes
        );
    }
}
