<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Data;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldType;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Tests\TestCase;

final class FieldTest extends TestCase
{
    private FieldFactory $f;

    private FieldTypes $types;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = new FieldFactory();
        $this->types = new FieldTypes();
        Functions\stubs([
            'sanitize_email' => static fn ($v) => (string) $v,
            'esc_url_raw' => static fn ($v) => (string) $v,
            'esc_textarea' => static fn ($v) => htmlspecialchars((string) $v),
            'sanitize_textarea_field' => static fn ($v) => (string) $v,
        ]);
    }

    public function test_fields_are_immutable(): void
    {
        $base = $this->f->text('isbn');
        $required = $base->required();

        self::assertFalse($base->isRequired());
        self::assertTrue($required->isRequired());
    }

    public function test_label_falls_back_to_a_readable_name(): void
    {
        self::assertSame('Release date', $this->f->date('release_date')->labelText());
        self::assertSame('ISBN', $this->f->text('isbn')->label('ISBN')->labelText());
    }

    public function test_invalid_field_names_are_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        Field::make('Not Valid', 'text');
    }

    public function test_number_keeps_negative_values_that_0x_lost(): void
    {
        $number = $this->types->get('number');

        self::assertSame(-5, $number->sanitize('-5', $this->f->number('balance')));
        self::assertSame(2.5, $number->sanitize('2.5', $this->f->number('rating')));
    }

    public function test_checkbox_reads_0x_values_and_stores_explicit_values(): void
    {
        $checkbox = $this->types->get('checkbox');
        $field = $this->f->checkbox('active');

        foreach (['on', '1', 'yes', 'true'] as $stored0x) {
            self::assertTrue($checkbox->read($stored0x, $field), $stored0x);
        }
        self::assertFalse($checkbox->read('0', $field));
        self::assertSame('1', $checkbox->sanitize('on', $field));
        self::assertSame('0', $checkbox->sanitize('0', $field));
    }

    public function test_choices_reject_values_that_are_not_options(): void
    {
        $field = $this->f->select('format', ['ranking' => 'Ranking', 'reel' => 'Reel']);
        $rules = $this->types->get('select')->rules($field);

        self::assertTrue($rules[0]->passes('reel', []));
        self::assertFalse($rules[0]->passes('<script>', []));
    }

    public function test_the_0x_media_type_name_still_works(): void
    {
        self::assertSame($this->types->get('media'), $this->types->get('wp_media'));
        self::assertSame(42, $this->types->get('media')->sanitize('42', $this->f->media('poster')));
        self::assertNull($this->types->get('media')->sanitize('0', $this->f->media('poster')));
    }

    public function test_sensitive_and_password_fields_never_render_their_value(): void
    {
        $html = $this->types->get('text')->render($this->f->text('api_key')->sensitive(), 'api_key', 'api-key', 'sk_live_123');

        self::assertStringNotContainsString('sk_live_123', $html);
        self::assertStringContainsString('placeholder="Saved — leave blank to keep"', $html);
        self::assertStringNotContainsString('hunter2', $this->types->get('password')->render($this->f->password('pw'), 'pw', 'pw', 'hunter2'));
    }

    public function test_rendered_values_and_options_are_escaped(): void
    {
        $text = $this->types->get('text')->render($this->f->text('title'), 'title', 'title', '"><script>x</script>');
        $select = $this->types->get('select')->render($this->f->select('s', ['a' => '<b>A</b>']), 's', 's', 'a');

        self::assertStringNotContainsString('<script>', $text);
        self::assertStringNotContainsString('<b>', $select);
        self::assertStringContainsString('selected', $select);
    }

    public function test_multiple_select_submits_an_array(): void
    {
        $html = $this->types->get('select')->render($this->f->select('tags', ['a' => 'A'])->multiple(), 'tags', 'tags', ['a']);

        self::assertStringContainsString('name="tags[]"', $html);
        self::assertStringContainsString(' multiple', $html);
    }

    public function test_custom_types_plug_in_without_changing_the_library(): void
    {
        $stars = new class implements FieldType {
            public function sanitize(mixed $value, Field $field): mixed
            {
                return max(1, min(5, (int) $value));
            }

            public function read(mixed $stored, Field $field): mixed
            {
                return (int) $stored;
            }

            public function rules(Field $field): array
            {
                return ['integer'];
            }

            public function render(Field $field, string $name, string $id, mixed $value): string
            {
                return '<stars></stars>';
            }
        };

        $this->types->register('stars', $stars);

        self::assertSame(5, $this->types->get('stars')->sanitize('9', $this->f->of('rating', 'stars')));
    }

    public function test_unknown_types_are_explained(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unknown field type "map"');

        $this->types->get('map');
    }
}
