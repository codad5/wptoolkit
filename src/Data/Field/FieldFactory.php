<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field;

/**
 * Named constructors for fields (Factory), so definitions read naturally:
 *
 *     [$f->text('isbn')->required(), $f->number('pages')->rules('min:1'), $f->of('rating', 'stars')]
 */
final class FieldFactory
{
    public function text(string $name): Field
    {
        return Field::make($name, 'text');
    }

    public function textarea(string $name): Field
    {
        return Field::make($name, 'textarea');
    }

    public function email(string $name): Field
    {
        return Field::make($name, 'email');
    }

    public function url(string $name): Field
    {
        return Field::make($name, 'url');
    }

    public function tel(string $name): Field
    {
        return Field::make($name, 'tel');
    }

    public function number(string $name): Field
    {
        return Field::make($name, 'number');
    }

    public function date(string $name): Field
    {
        return Field::make($name, 'date');
    }

    public function color(string $name): Field
    {
        return Field::make($name, 'color');
    }

    public function hidden(string $name): Field
    {
        return Field::make($name, 'hidden');
    }

    public function password(string $name): Field
    {
        return Field::make($name, 'password')->sensitive();
    }

    /**
     * @param array<string|int, string> $options value => label
     */
    public function select(string $name, array $options): Field
    {
        return Field::make($name, 'select')->options($options);
    }

    /**
     * @param array<string|int, string> $options value => label
     */
    public function radio(string $name, array $options): Field
    {
        return Field::make($name, 'radio')->options($options);
    }

    public function checkbox(string $name): Field
    {
        return Field::make($name, 'checkbox');
    }

    public function media(string $name): Field
    {
        return Field::make($name, 'media');
    }

    public function wysiwyg(string $name): Field
    {
        return Field::make($name, 'wysiwyg');
    }

    /**
     * A field of any registered type, including your own.
     */
    public function of(string $name, string $type): Field
    {
        return Field::make($name, $type);
    }
}
