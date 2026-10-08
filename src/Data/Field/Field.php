<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Support\Validation\Rule;

/**
 * An immutable field definition. Each fluent call returns a new Field:
 *
 *     $f->text('isbn')->label(__('ISBN', 'my-plugin'))->required()->rules('max:17')
 *
 * The field's type (a FieldType registered in FieldTypes) decides how it is sanitized, validated,
 * rendered and stored.
 */
final class Field
{
    /**
     * @param array<string, mixed> $settings
     */
    private function __construct(
        public readonly string $name,
        public readonly string $type,
        private readonly array $settings = []
    ) {
    }

    public static function make(string $name, string $type): self
    {
        if (preg_match('/^[a-z0-9_\-]+$/', $name) !== 1) {
            throw new InvalidConfigException(sprintf('Field name "%s" may contain only lowercase letters, digits, "_" and "-".', $name));
        }

        return new self($name, $type);
    }

    public function label(string $label): self
    {
        return $this->with('label', $label);
    }

    public function description(string $description): self
    {
        return $this->with('description', $description);
    }

    public function required(bool $required = true): self
    {
        return $this->with('required', $required);
    }

    public function default(mixed $value): self
    {
        return $this->with('default', $value);
    }

    /**
     * Choices for select, radio and multi-checkbox fields: value => label.
     *
     * @param array<string|int, string> $options
     */
    public function options(array $options): self
    {
        return $this->with('options', $options);
    }

    /**
     * Several values (a multi-select, several media items), stored one meta row per value.
     */
    public function multiple(bool $multiple = true): self
    {
        return $this->with('multiple', $multiple);
    }

    /**
     * A secret (API key, token): never in bulk reads, JSON or HTML (ADR-0021).
     */
    public function sensitive(bool $sensitive = true): self
    {
        return $this->with('sensitive', $sensitive);
    }

    /**
     * Extra validation rules on top of the type's own: `'max:120'`, or Rule objects.
     *
     * @param string|list<string|Rule> $rules
     */
    public function rules(string|array $rules): self
    {
        return $this->with('rules', $rules);
    }

    /**
     * Extra HTML attributes for the input (`placeholder`, `min`, `step`, `class` …).
     *
     * @param array<string, string|int|float|bool> $attributes
     */
    public function attributes(array $attributes): self
    {
        return $this->with('attributes', $attributes);
    }

    public function quickEdit(bool $quickEdit = true): self
    {
        return $this->with('quick_edit', $quickEdit);
    }

    /**
     * A setting for custom field types.
     */
    public function with(string $key, mixed $value): self
    {
        return new self($this->name, $this->type, [$key => $value] + $this->settings);
    }

    // --- Read side ---------------------------------------------------------------------------

    public function setting(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->settings) ? $this->settings[$key] : $default;
    }

    public function labelText(): string
    {
        $label = $this->setting('label');

        return is_string($label) && $label !== '' ? $label : ucfirst(str_replace(['_', '-'], ' ', $this->name));
    }

    public function isRequired(): bool
    {
        return (bool) $this->setting('required', false);
    }

    public function isMultiple(): bool
    {
        return (bool) $this->setting('multiple', false);
    }

    /**
     * How a multiple field is stored, as 0.x did (ADR-0016): media fields one meta row per ID,
     * every other multiple field one row holding a serialized array.
     */
    public function storesOneRowPerValue(): bool
    {
        return $this->isMultiple() && in_array($this->type, ['media', 'wp_media'], true);
    }

    public function isSensitive(): bool
    {
        return (bool) $this->setting('sensitive', false);
    }

    public function defaultValue(): mixed
    {
        return $this->setting('default', $this->isMultiple() ? [] : null);
    }

    /**
     * @return array<string|int, string>
     */
    public function choices(): array
    {
        $options = $this->setting('options', []);

        return is_array($options) ? $options : [];
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    public function htmlAttributes(): array
    {
        $attributes = $this->setting('attributes', []);

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * @return list<string|Rule>
     */
    public function extraRules(): array
    {
        $rules = $this->setting('rules', []);
        if (is_string($rules)) {
            return array_values(array_filter(explode('|', $rules), static fn (string $r): bool => $r !== ''));
        }

        return is_array($rules) ? array_values($rules) : [];
    }
}
