<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field;

use Codad5\WPToolkit\Support\Validation\Rules;

/**
 * Validates values against field definitions: required first, then the type's rules and the
 * field's own, applied to every value of a multiple field. Shared by MetaBox and the repositories.
 */
final class FieldValidator
{
    public function __construct(private readonly FieldTypes $types)
    {
    }

    /**
     * @return list<string> Error messages; empty means valid.
     */
    public function validate(Field $field, mixed $value): array
    {
        $label = $field->labelText();

        if ($field->isRequired() && Rules::isEmpty($value)) {
            return [Rules::required()->message($label)];
        }

        $rules = Rules::parse([...$this->types->get($field->type)->rules($field), ...$field->extraRules()]);
        $items = $field->isMultiple() ? (is_array($value) ? $value : [$value]) : [$value];
        $errors = [];
        foreach ($items as $item) {
            foreach ($rules as $rule) {
                if (!$rule->passes($item, [])) {
                    $errors[] = $rule->message($label);
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string, Field> $fields
     * @param array<string, mixed> $values
     * @return array<string, list<string>> Errors per field name; only fields present in $values are checked.
     */
    public function validateAll(array $fields, array $values): array
    {
        $errors = [];
        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $values)) {
                continue;
            }
            $messages = $this->validate($field, $values[$name]);
            if ($messages !== []) {
                $errors[$name] = $messages;
            }
        }

        return $errors;
    }
}
