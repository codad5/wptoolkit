<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Data;

use Brain\Monkey\Actions;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Field\FieldValidator;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Tests\TestCase;

/**
 * Multiple-choice fields: a plain <select multiple> selects one item per click and can't be cleared
 * (nothing selected submits nothing), so they render as checkbox lists, enhanced into a searchable
 * chip picker. The checkboxes stay the source of truth.
 */
final class MultipleChoiceTest extends TestCase
{
    private FieldFactory $f;

    private FieldTypes $types;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = new FieldFactory();
        $this->types = new FieldTypes();
    }

    /**
     * @param array<string, string> $options
     */
    private function render(array $options, mixed $value = [], ?string $display = null, string $name = 'tags'): string
    {
        $field = $this->f->select($name, $options)->multiple();
        if ($display !== null) {
            $field = $field->display($display);
        }

        return $this->types->get('select')->render($field->label('Tags'), $name, $name, $value);
    }

    public function test_a_short_list_is_a_checkbox_group_that_submits_an_array(): void
    {
        $html = $this->render(['a' => 'Alpha', 'b' => 'Beta'], ['b']);

        self::assertStringContainsString('<fieldset', $html);
        self::assertSame(2, substr_count($html, 'type="checkbox"'));
        self::assertSame(3, substr_count($html, 'name="tags[]"'), 'two checkboxes and the empty marker');
        self::assertStringContainsString('data-wptoolkit-picker="checkboxes"', $html);
        self::assertMatchesRegularExpression('/value="b"[^>]* checked/', $html);
        self::assertDoesNotMatchRegularExpression('/value="a"[^>]* checked/', $html);
        self::assertStringNotContainsString('<select', $html);
    }

    public function test_a_long_list_becomes_the_token_picker(): void
    {
        $options = array_combine(range('a', 'h'), range('A', 'H'));

        self::assertStringContainsString('data-wptoolkit-picker="tokens"', $this->render($options));
    }

    public function test_the_mode_can_be_chosen_explicitly(): void
    {
        $options = ['a' => 'A', 'b' => 'B'];

        self::assertStringContainsString('data-wptoolkit-picker="tokens"', $this->render($options, [], 'tokens'));
        self::assertStringContainsString('<select', $this->render($options, [], 'native'));
        self::assertStringContainsString(' multiple', $this->render($options, [], 'native'));
    }

    public function test_an_unknown_mode_is_refused_when_the_field_is_defined(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->f->select('tags', ['a' => 'A'])->multiple()->display('fancy');
    }

    public function test_every_display_sends_an_empty_marker_so_deselecting_everything_is_saved(): void
    {
        foreach ([null, 'checkboxes', 'tokens', 'native'] as $display) {
            $html = $this->render(['a' => 'A', 'b' => 'B'], ['a'], $display);

            self::assertStringContainsString('<input type="hidden" name="tags[]" value="" />', $html, (string) $display);
            self::assertLessThan(
                strpos($html, 'name="tags[]"', strpos($html, 'type="hidden"') + 10) ?: PHP_INT_MAX,
                strpos($html, 'type="hidden"'),
                'the marker comes first (' . $display . ')'
            );
        }
    }

    public function test_options_values_and_labels_are_escaped_and_ids_are_unique(): void
    {
        $html = $this->render(['x"><script>alert(1)</script>' => '<img src=x onerror=alert(1)>', 'plain' => 'Plain'], [], 'checkboxes');

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
        preg_match_all('/ id="([^"]+)"/', $html, $ids);
        self::assertSame($ids[1], array_values(array_unique($ids[1])), 'no duplicate ids, so every label points at its own checkbox');
    }

    public function test_each_checkbox_has_a_label_and_the_group_is_named(): void
    {
        $html = $this->render(['a' => 'Alpha'], [], 'checkboxes');

        self::assertMatchesRegularExpression('/<label[^>]*>.*<input[^>]*type="checkbox".*Alpha/s', $html);
        self::assertStringContainsString('Tags</legend>', $html);
    }

    public function test_a_single_select_and_radio_are_unchanged(): void
    {
        $single = $this->types->get('select')->render($this->f->select('size', ['s' => 'S', 'm' => 'M']), 'size', 'size', 'm');

        self::assertStringStartsWith('<select', $single);
        self::assertStringNotContainsString('hidden', $single);
        self::assertStringContainsString('type="radio"', $this->types->get('radio')->render($this->f->radio('r', ['a' => 'A']), 'r', 'r', 'a'));
    }

    public function test_the_picker_script_and_style_are_printed_once_in_the_footer_however_many_fields(): void
    {
        $printers = [];
        Actions\expectAdded('admin_print_footer_scripts')->zeroOrMoreTimes()->whenHappen(static function ($callback) use (&$printers): void {
            $printers[] = $callback;
        });
        Actions\expectAdded('wp_print_footer_scripts')->zeroOrMoreTimes()->whenHappen(static function ($callback) use (&$printers): void {
            $printers[] = $callback;
        });
        $options = array_combine(range('a', 'h'), range('A', 'H'));

        $this->render($options, [], null, 'one');
        $this->render($options, [], null, 'two');
        $this->render(['a' => 'A'], [], 'checkboxes', 'three');

        self::assertNotEmpty($printers, 'assets are queued for the footer, not printed inline');
        ob_start();
        foreach ($printers as $printer) {
            $printer();
        }
        $output = (string) ob_get_clean();

        self::assertSame(1, substr_count($output, '<script'));
        self::assertSame(1, substr_count($output, '<style'));
        self::assertSame(1, substr_count($output, '</script>'), 'the script holds no closing tag that would end it early');
    }

    public function test_checkbox_only_lists_need_no_script(): void
    {
        $printers = [];
        Actions\expectAdded('admin_print_footer_scripts')->zeroOrMoreTimes()->whenHappen(static function ($callback) use (&$printers): void {
            $printers[] = $callback;
        });

        $this->render(['a' => 'A', 'b' => 'B'], [], 'checkboxes');

        self::assertSame([], $printers);
    }

    public function test_empty_entries_are_ignored_when_validating_a_multiple_field(): void
    {
        $validator = new FieldValidator($this->types);
        $field = $this->f->select('tags', ['a' => 'A', 'b' => 'B'])->multiple();

        self::assertSame([], $validator->validate($field, ['', 'a']), 'the empty marker is not a choice');
        self::assertSame([], $validator->validate($field, ['']), 'nothing chosen is fine when the field is optional');
        self::assertNotSame([], $validator->validate($field->required(), ['']), 'and counts as nothing when it is required');
        self::assertNotSame([], $validator->validate($field, ['', 'zzz']), 'a real bad value is still caught');
    }
}
