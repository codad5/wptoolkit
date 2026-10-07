<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Admin\Column;
use Codad5\WPToolkit\Admin\Columns;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\MetaBox;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Support\FakePostMeta;
use Codad5\WPToolkit\Tests\TestCase;
use WP_Query;

final class ColumnsTest extends TestCase
{
    private FakePostMeta $wp;

    private MetaBox $box;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wp = new FakePostMeta();
        $this->wp->install();
        Functions\stubs([
            'number_format_i18n' => static fn ($n, $d = 0) => number_format((float) $n, (int) $d),
            'date_i18n' => static fn ($format, $time) => gmdate((string) $format, (int) $time),
            'get_option' => static fn ($name) => $name === 'date_format' ? 'j M Y' : false,
            'sanitize_html_class' => static fn ($c) => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c),
        ]);

        $f = new FieldFactory();
        $this->box = new MetaBox('event_details', 'Event details', 'pau-event', [
            $f->date('start_date')->label('Start Date')->quickEdit(),
            $f->number('max_attendees'),
            $f->number('ticket_price'),
            $f->text('venue_name'),
            $f->select('format', ['online' => 'Online', 'hall' => 'In the hall']),
            $f->checkbox('featured'),
        ], new FieldTypes(), new Identity('pau'), new ArrayStore(new FrozenClock()));
    }

    public function test_columns_are_placed_like_0x_and_named_by_meta_key(): void
    {
        $columns = $this->columns([
            Column::field('start_date')->position(Column::AFTER_TITLE),
            Column::field('venue_name')->position(Column::AFTER_DATE)->label('Venue'),
            Column::field('max_attendees'),
        ]);

        $result = $columns->addColumns(['cb' => '', 'title' => 'Title', 'date' => 'Date']);

        self::assertSame(
            ['cb', 'title', 'event_details_pau-event_start_date', 'date', 'event_details_pau-event_venue_name', 'event_details_pau-event_max_attendees'],
            array_keys($result)
        );
        self::assertSame('Start Date', $result['event_details_pau-event_start_date']);
        self::assertSame('Venue', $result['event_details_pau-event_venue_name']);
    }

    public function test_cells_are_formatted_and_escaped(): void
    {
        $this->wp->meta[3] = [
            'event_details_pau-event_start_date' => ['2026-10-07'],
            'event_details_pau-event_max_attendees' => ['1500'],
            'event_details_pau-event_ticket_price' => ['25'],
            'event_details_pau-event_venue_name' => ['<script>x</script> Hall'],
            'event_details_pau-event_format' => ['hall'],
            'event_details_pau-event_featured' => ['on'],
        ];
        $columns = $this->columns([
            Column::field('start_date'),
            Column::field('max_attendees'),
            Column::field('ticket_price')->format(Column::CURRENCY, '₦'),
            Column::field('venue_name'),
            Column::field('format'),
            Column::field('featured'),
        ]);

        self::assertSame('7 Oct 2026', $this->cell($columns, 'start_date', 3));
        self::assertSame('1,500', $this->cell($columns, 'max_attendees', 3));
        self::assertSame('₦25.00', $this->cell($columns, 'ticket_price', 3));
        self::assertStringNotContainsString('<script>', $this->cell($columns, 'venue_name', 3));
        self::assertSame('In the hall', $this->cell($columns, 'format', 3));
        self::assertSame('Yes', $this->cell($columns, 'featured', 3));
        self::assertStringContainsString('—', $this->cell($columns, 'max_attendees', 99), 'empty cells show a dash');
    }

    public function test_sortable_columns_sort_by_their_meta_value(): void
    {
        $columns = $this->columns([Column::field('max_attendees')->sortable(), Column::field('venue_name')]);
        Functions\when('is_admin')->justReturn(true);

        self::assertSame(['event_details_pau-event_max_attendees' => 'event_details_pau-event_max_attendees'], $columns->sortableColumns([]));

        $query = new WP_Query(['post_type' => 'pau-event', 'orderby' => 'event_details_pau-event_max_attendees']);
        $columns->applySort($query);
        self::assertSame('meta_value_num', $query->get('orderby'));
        self::assertSame('event_details_pau-event_max_attendees', $query->get('meta_key'));

        $other = new WP_Query(['post_type' => 'post', 'orderby' => 'event_details_pau-event_max_attendees']);
        $columns->applySort($other);
        self::assertNull($other->get('meta_key'), 'other post types are untouched');
    }

    public function test_the_quick_edit_field_column_is_the_one_the_meta_box_answers_to(): void
    {
        $columns = $this->columns([Column::field('start_date')]);

        self::assertSame(array_keys($this->box->quickEditFields()), array_keys($columns->columns()));
    }

    public function test_bad_configuration_is_refused(): void
    {
        foreach ([
            fn () => $this->columns([Column::field('nope')]),
            static fn () => Column::field('a')->position('middle'),
            static fn () => Column::field('a')->width('wide; color:red'),
            static fn () => Column::field('a')->format('stars'),
        ] as $i => $bad) {
            try {
                $bad();
                self::fail('case ' . $i);
            } catch (InvalidConfigException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @param list<Column> $columns
     */
    private function columns(array $columns): Columns
    {
        return new Columns($this->box, $columns);
    }

    private function cell(Columns $columns, string $field, int $postId): string
    {
        ob_start();
        $columns->renderCell($this->box->metaKey($field), $postId);

        return (string) ob_get_clean();
    }
}
