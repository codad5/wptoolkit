<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field;

use Codad5\WPToolkit\Data\Field\Types\ChoiceType;
use Codad5\WPToolkit\Data\Field\Types\InputType;
use Codad5\WPToolkit\Data\Field\Types\OtherTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * The field types one application knows (Open/Closed: add types, never edit these). A singleton in
 * each application's container, so two plugins' custom types never meet.
 *
 *     $types->register('stars', new StarRatingType());
 */
final class FieldTypes
{
    /** @var array<string, FieldType> */
    private array $types = [];

    public function __construct()
    {
        $this->types = [
            'text' => new InputType('text'),
            'email' => new InputType('email', 'email', ['email']),
            'url' => new InputType('url', 'url', ['url']),
            'tel' => new InputType('tel'),
            'number' => new InputType('number', 'raw', ['numeric']),
            'date' => new InputType('date', 'text', ['regex:/^\d{4}-\d{2}-\d{2}$/']),
            'color' => new InputType('color', 'text', ['regex:/^#[0-9a-fA-F]{6}$/']),
            'hidden' => new InputType('hidden'),
            'password' => new InputType('password', 'raw'),
            'textarea' => OtherTypes::textarea(),
            'select' => new ChoiceType('select'),
            'radio' => new ChoiceType('radio'),
            'checkbox' => OtherTypes::checkbox(),
            'media' => OtherTypes::media(),
            'wysiwyg' => OtherTypes::wysiwyg(),
        ];
        // 0.x name, kept so existing definitions keep working.
        $this->types['wp_media'] = $this->types['media'];
    }

    public function register(string $name, FieldType $type): self
    {
        if (preg_match('/^[a-z0-9_\-]+$/', $name) !== 1) {
            throw new InvalidConfigException(sprintf('Field type name "%s" may contain only lowercase letters, digits, "_" and "-".', $name));
        }

        $this->types[$name] = $type;

        return $this;
    }

    public function get(string $name): FieldType
    {
        return $this->types[$name]
            ?? throw new InvalidConfigException(sprintf('Unknown field type "%s". Register it with FieldTypes::register().', $name));
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }
}
