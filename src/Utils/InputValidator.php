<?php

/**
 * Enhanced Input Validator
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * InputValidator class for comprehensive field validation.
 * 
 * Handles validation for various input types with extensible validation rules
 * and custom validator support.
 */
class InputValidator
{
    /**
     * Custom validation rules registry.
     *
     * @var array<string, callable>
     */
    private static array $custom_validators = [];

    /**
     * Global validation rules that apply to all fields.
     *
     * @var array<callable>
     */
    private static array $global_validators = [];

    /**
     * Validation error messages.
     *
     * @var array<string, string>
     */
    private static array $error_messages = [
        'required' => 'This field is required.',
        'invalid_type' => 'Invalid field type.',
        'invalid_email' => 'Please enter a valid email address.',
        'invalid_url' => 'Please enter a valid URL.',
        'invalid_date' => 'Please enter a valid date.',
        'invalid_color' => 'Please enter a valid color code.',
        'invalid_number' => 'Please enter a valid number.',
        'min_value' => 'Value must be at least {min}.',
        'max_value' => 'Value must not exceed {max}.',
        'min_length' => 'Must be at least {min} characters long.',
        'max_length' => 'Must not exceed {max} characters.',
        'invalid_option' => 'Please select a valid option.',
        'invalid_file' => 'Invalid file type or file not found.',
        'max_files' => 'Maximum {max} files allowed.',
        'file_too_large' => 'File size exceeds the maximum limit.',
        'invalid_media' => 'Invalid media attachment.'
    ];

    /**
     * Validate a field value based on its type and configuration.
     *
     * @param string $type The input type
     * @param mixed $value The value to validate
     * @param array $field Field configuration
     * @return bool|string True if valid, error message string if invalid
     */
    public static function validate(string $type, mixed $value, array $field): bool|string
    {
        // Check if field is required
        if (self::is_required($field) && self::is_empty($value)) {
            return self::get_error_message('required', $field);
        }

        // Skip validation for empty optional fields
        if (!self::is_required($field) && self::is_empty($value)) {
            return true;
        }

        // Run global validators first
        foreach (self::$global_validators as $validator) {
            $result = $validator($value, $field, $type);
            if ($result !== true) {
                return is_string($result) ? $result : self::get_error_message('invalid_type', $field);
            }
        }

        // Check for custom validator for this type
        if (isset(self::$custom_validators[$type])) {
            $result = self::$custom_validators[$type]($value, $field);
            return $result !== true ? (is_string($result) ? $result : self::get_error_message('invalid_type', $field)) : true;
        }

        // Run built-in validation
        return self::validate_by_type($type, $value, $field);
    }

    /**
     * Validate value by its type.
     *
     * @param string $type Field type
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string True if valid, error message if invalid
     */
    private static function validate_by_type(string $type, mixed $value, array $field): bool|string
    {
        return match ($type) {
            'text', 'textarea', 'hidden', 'password' => self::validate_text($value, $field),
            'number' => self::validate_number($value, $field),
            'email' => self::validate_email($value, $field),
            'url' => self::validate_url($value, $field),
            'date', 'datetime-local', 'time' => self::validate_date($value, $field),
            'color' => self::validate_color($value, $field),
            'select', 'radio' => self::validate_select($value, $field),
            'checkbox' => self::validate_checkbox($value, $field),
            'file' => self::validate_file($value, $field),
            'wp_media' => self::validate_wp_media($value, $field),
            'tel' => self::validate_phone($value, $field),
            default => self::validate_custom_or_default($type, $value, $field)
        };
    }

    /**
     * Validate text input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_text(mixed $value, array $field): bool|string
    {
        if (!is_string($value)) {
            return self::get_error_message('invalid_type', $field);
        }

        // Check min/max length
        $length = strlen($value);

        if (isset($field['attributes']['minlength']) && $length < $field['attributes']['minlength']) {
            return self::get_error_message('min_length', $field, ['min' => $field['attributes']['minlength']]);
        }

        if (isset($field['attributes']['maxlength']) && $length > $field['attributes']['maxlength']) {
            return self::get_error_message('max_length', $field, ['max' => $field['attributes']['maxlength']]);
        }

        // Check pattern if provided
        if (isset($field['attributes']['pattern'])) {
            $pattern = $field['attributes']['pattern'];
            if (!preg_match("/{$pattern}/", $value)) {
                return self::get_error_message('invalid_type', $field);
            }
        }

        return true;
    }

    /**
     * Validate number input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_number(mixed $value, array $field): bool|string
    {
        if (!is_numeric($value)) {
            return self::get_error_message('invalid_number', $field);
        }

        $numValue = is_string($value) ? (float)$value : $value;

        if (isset($field['attributes']['min']) && $numValue < $field['attributes']['min']) {
            return self::get_error_message('min_value', $field, ['min' => $field['attributes']['min']]);
        }

        if (isset($field['attributes']['max']) && $numValue > $field['attributes']['max']) {
            return self::get_error_message('max_value', $field, ['max' => $field['attributes']['max']]);
        }

        if (isset($field['attributes']['step'])) {
            $step = $field['attributes']['step'];
            $min = $field['attributes']['min'] ?? 0;
            if (fmod(($numValue - $min), $step) !== 0.0) {
                return self::get_error_message('invalid_number', $field);
            }
        }

        return true;
    }

    /**
     * Validate email input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_email(mixed $value, array $field): bool|string
    {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return self::get_error_message('invalid_email', $field);
        }

        return self::validate_text($value, $field);
    }

    /**
     * Validate URL input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_url(mixed $value, array $field): bool|string
    {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            return self::get_error_message('invalid_url', $field);
        }

        return self::validate_text($value, $field);
    }

    /**
     * Validate date input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_date(mixed $value, array $field): bool|string
    {
        if (!is_string($value) || strtotime($value) === false) {
            return self::get_error_message('invalid_date', $field);
        }

        // Check date range if specified
        if (isset($field['attributes']['min'])) {
            $minTime = strtotime($field['attributes']['min']);
            $valueTime = strtotime($value);
            if ($minTime !== false && $valueTime !== false && $valueTime < $minTime) {
                return self::get_error_message('min_value', $field, ['min' => $field['attributes']['min']]);
            }
        }

        if (isset($field['attributes']['max'])) {
            $maxTime = strtotime($field['attributes']['max']);
            $valueTime = strtotime($value);
            if ($maxTime !== false && $valueTime !== false && $valueTime > $maxTime) {
                return self::get_error_message('max_value', $field, ['max' => $field['attributes']['max']]);
            }
        }

        return true;
    }

    /**
     * Validate color input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_color(mixed $value, array $field): bool|string
    {
        if (!is_string($value) || !preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
            return self::get_error_message('invalid_color', $field);
        }

        return true;
    }

    /**
     * Validate select/radio input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_select(mixed $value, array $field): bool|string
    {
        $options = $field['options'] ?? [];

        if (empty($options)) {
            return true; // No options to validate against
        }

        // Handle multiple select
        if (is_array($value)) {
            foreach ($value as $v) {
                if (!array_key_exists($v, $options)) {
                    return self::get_error_message('invalid_option', $field);
                }
            }
            return true;
        }

        if (!array_key_exists($value, $options)) {
            return self::get_error_message('invalid_option', $field);
        }

        return true;
    }

    /**
     * Validate checkbox input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_checkbox(mixed $value, array $field): bool|string
    {
        // Checkbox values can be boolean or string representations
        if (!is_bool($value) && !in_array($value, ['1', '0', 'true', 'false', 'on', ''], true)) {
            return self::get_error_message('invalid_type', $field);
        }

        return true;
    }

    /**
     * Validate phone number input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_phone(mixed $value, array $field): bool|string
    {
        if (!is_string($value)) {
            return self::get_error_message('invalid_type', $field);
        }

        // Basic phone validation - can be enhanced
        $cleaned = preg_replace('/[^0-9+\-\s\(\)]/', '', $value);
        if (strlen($cleaned) < 10) {
            return self::get_error_message('invalid_type', $field);
        }

        return self::validate_text($value, $field);
    }

    /**
     * Validate file input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_file(mixed $value, array $field): bool|string
    {
        if (!is_array($value) || !isset($value['tmp_name'], $value['name'])) {
            return self::get_error_message('invalid_file', $field);
        }

        // Check file size
        if (isset($field['attributes']['max_size']) && isset($value['size'])) {
            if ($value['size'] > $field['attributes']['max_size']) {
                return self::get_error_message('file_too_large', $field);
            }
        }

        // Check file type
        $allowed_types = $field['attributes']['accept'] ?? [];
        if (!empty($allowed_types)) {
            $file_type = mime_content_type($value['tmp_name']);
            if (!in_array($file_type, $allowed_types, true)) {
                return self::get_error_message('invalid_file', $field);
            }
        }

        return true;
    }

    /**
     * Validate WordPress media input.
     *
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_wp_media(mixed $value, array $field): bool|string
    {
        // Handle both single and multiple media
        $values = is_array($value) ? $value : [$value];

        // Remove empty values
        $values = array_filter($values, fn($v) => !empty($v));

        // Check if required but empty
        if (self::is_required($field) && empty($values)) {
            return self::get_error_message('required', $field);
        }

        // Check maximum number of files
        if (isset($field['attributes']['max']) && count($values) > $field['attributes']['max']) {
            return self::get_error_message('max_files', $field, ['max' => $field['attributes']['max']]);
        }

        // Validate each attachment
        foreach ($values as $attachment_id) {
            if (!is_numeric($attachment_id) || !wp_get_attachment_url($attachment_id)) {
                return self::get_error_message('invalid_media', $field);
            }
        }

        return true;
    }

    /**
     * Handle custom validation or return default.
     *
     * @param string $type Field type
     * @param mixed $value Value to validate
     * @param array $field Field configuration
     * @return bool|string
     */
    private static function validate_custom_or_default(string $type, mixed $value, array $field): bool|string
    {
        // Check if field has a custom validate_callback
        if (isset($field['validate_callback']) && is_callable($field['validate_callback'])) {
            $result = $field['validate_callback']($value, $field);
            return $result !== true ? (is_string($result) ? $result : self::get_error_message('invalid_type', $field)) : true;
        }

        // Default to valid for unknown types
        return true;
    }

    /**
     * Register a custom validator for a specific type.
     *
     * @param string $type Field type
     * @param callable $validator Validation function
     * @return void
     */
    public static function register_validator(string $type, callable $validator): void
    {
        self::$custom_validators[$type] = $validator;
    }

    /**
     * Register a global validator that runs for all fields.
     *
     * @param callable $validator Validation function
     * @return void
     */
    public static function add_global_validator(callable $validator): void
    {
        self::$global_validators[] = $validator;
    }

    /**
     * Set custom error message for a validation type.
     *
     * @param string $type Error type
     * @param string $message Error message
     * @return void
     */
    public static function set_error_message(string $type, string $message): void
    {
        self::$error_messages[$type] = $message;
    }

    /**
     * Get error message with placeholder replacement.
     *
     * @param string $type Error type
     * @param array $field Field configuration
     * @param array $replacements Placeholder replacements
     * @return string
     */
    private static function get_error_message(string $type, array $field, array $replacements = []): string
    {
        // Check for custom field error message first
        if (isset($field['error_messages'][$type])) {
            $message = $field['error_messages'][$type];
        } elseif (isset($field['error_message']) && $type === 'invalid_type') {
            $message = $field['error_message'];
        } else {
            $message = self::$error_messages[$type] ?? self::$error_messages['invalid_type'];
        }

        // Replace placeholders
        foreach ($replacements as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Check if field is required.
     *
     * @param array $field Field configuration
     * @return bool
     */
    private static function is_required(array $field): bool
    {
        return $field['required'] ?? $field['attributes']['required'] ?? false;
    }

    /**
     * Check if value is considered empty.
     *
     * @param mixed $value Value to check
     * @return bool
     */
    private static function is_empty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return empty(array_filter($value, fn($v) => $v !== '' && $v !== null));
        }

        return false;
    }

    /**
     * Validate multiple values at once.
     *
     * @param array $values Array of [type => value] or [type => [value, field]]
     * @param array $fields Optional field configurations
     * @return array Array of validation results
     */
    public static function validate_many(array $values, array $fields = []): array
    {
        $results = [];

        foreach ($values as $key => $data) {
            if (is_array($data) && count($data) === 2) {
                [$type, $value] = $data;
                $field = $fields[$key] ?? [];
            } else {
                $type = $key;
                $value = $data;
                $field = $fields[$key] ?? [];
            }

            $results[$key] = self::validate($type, $value, $field);
        }

        return $results;
    }

    /**
     * Get all registered custom validators.
     *
     * @return array
     */
    public static function get_custom_validators(): array
    {
        return self::$custom_validators;
    }

    /**
     * Clear all custom validators.
     *
     * @return void
     */
    public static function clear_custom_validators(): void
    {
        self::$custom_validators = [];
    }

    /**
     * Clear all global validators.
     *
     * @return void
     */
    public static function clear_global_validators(): void
    {
        self::$global_validators = [];
    }
}
