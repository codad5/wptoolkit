<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\Validation;

/**
 * Checks input against declared rules and sanitizes it by declared type. Only declared fields
 * come out — undeclared input never reaches the controller through `Request::input()`.
 */
final class Validator
{
    /**
     * @param array<string, array{rules?: string|list<string|Rule>, type?: string, label?: string, default?: mixed}> $fields
     */
    public function __construct(private readonly array $fields)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>} Sanitized values, and
     *         errors keyed by field (empty when valid).
     */
    public function validate(array $input): array
    {
        $values = [];
        $errors = [];

        foreach ($this->fields as $field => $definition) {
            $type = $definition['type'] ?? 'text';
            $raw = array_key_exists($field, $input) ? $input[$field] : ($definition['default'] ?? null);
            $label = $definition['label'] ?? $field;

            foreach (Rules::parse($definition['rules'] ?? []) as $rule) {
                if (!$rule->passes($raw, $input)) {
                    $errors[$field][] = $rule->message($label);
                }
            }

            if (!isset($errors[$field])) {
                $values[$field] = Rules::isEmpty($raw) ? $raw : Sanitizer::sanitize($raw, $type);
            }
        }

        return [$values, $errors];
    }
}
