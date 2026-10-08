<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Data;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\MetaBox;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Support\FakePostMeta;
use Codad5\WPToolkit\Tests\TestCase;
use WP_Post;

final class MetaBoxTest extends TestCase
{
    private FakePostMeta $wp;

    private FieldFactory $f;

    private ArrayStore $flash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wp = new FakePostMeta();
        $this->wp->install();
        $this->f = new FieldFactory();
        $this->flash = new ArrayStore(new FrozenClock());
        Functions\stubs(['sanitize_email' => static fn ($v) => (string) $v, 'esc_url_raw' => static fn ($v) => (string) $v]);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('get_current_user_id')->justReturn(1);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    // --- Data compatibility (ADR-0016) --------------------------------------------------------

    public function test_reads_what_0x_stored_with_its_default_keys(): void
    {
        // Shaped like a 0.x plugin: box "executive_role" on post type "acme-executive", no custom prefix.
        $this->wp->meta[10] = [
            'executive_role_acme-executive_title' => ['President'],
            'executive_role_acme-executive_active' => ['on'],
        ];
        $box = $this->box('executive_role', 'acme-executive', [$this->f->text('title'), $this->f->checkbox('active')]);

        self::assertSame('President', $box->value(10, 'title'));
        self::assertTrue($box->value(10, 'active'));
    }

    public function test_reads_what_0x_stored_under_a_custom_prefix_including_multiple_media(): void
    {
        // Shaped like a 0.x theme: set_prefix('_acme_movies_'), wp_media multiple = one row per ID.
        $this->wp->meta[20] = [
            '_acme_movies_availability' => ['now_showing'],
            '_acme_movies_gallery' => ['11', '12', '13'],
        ];
        $box = $this->box('movie_details', 'acme_movies', [
            $this->f->select('availability', ['now_showing' => 'Now showing', 'coming_soon' => 'Coming soon']),
            $this->f->of('gallery', 'wp_media')->multiple(),
        ])->prefix('_acme_movies_');

        self::assertSame('now_showing', $box->value(20, 'availability'));
        self::assertSame([11, 12, 13], $box->value(20, 'gallery'));
    }

    public function test_writes_in_the_same_formats_so_0x_can_still_read_them(): void
    {
        $box = $this->box('movie_details', 'acme_movies', [
            $this->f->media('gallery')->multiple(),
            $this->f->text('title'),
        ])->prefix('_acme_movies_');

        $box->save(30, 'gallery', ['5', '6']);
        $box->save(30, 'title', 'Dune');

        self::assertSame([5, 6], $this->wp->meta[30]['_acme_movies_gallery'], 'one row per ID');
        self::assertSame(['Dune'], $this->wp->meta[30]['_acme_movies_title']);
    }

    public function test_backslashes_survive_a_save(): void
    {
        $box = $this->box('d', 'book', [$this->f->text('path')]);

        $box->save(1, 'path', 'C:\books\dune');

        self::assertSame(['C:\books\dune'], $this->wp->meta[1]['d_book_path']);
    }

    public function test_non_media_multiple_fields_keep_0x_serialized_array_shape(): void
    {
        // 0.x stored arrays from sanitize callbacks as one serialized row; only wp_media used rows.
        $this->wp->meta[40] = ['d_book_tags' => [['a', 'b']]];
        $box = $this->box('d', 'book', [$this->f->select('tags', ['a' => 'A', 'b' => 'B', 'c' => 'C'])->multiple()]);

        self::assertSame(['a', 'b'], $box->value(40, 'tags'));

        $box->save(41, 'tags', ['b', 'c']);
        self::assertSame([['b', 'c']], $this->wp->meta[41]['d_book_tags'], 'one row, so 0.x reads it after a downgrade');
    }

    // --- Reading and validation -------------------------------------------------------------

    public function test_missing_values_fall_back_to_defaults(): void
    {
        $box = $this->box('d', 'book', [$this->f->text('format')->default('paperback'), $this->f->media('images')->multiple()]);

        self::assertSame('paperback', $box->value(1, 'format'));
        self::assertSame([], $box->value(1, 'images'));
    }

    public function test_values_leave_out_sensitive_fields(): void
    {
        $this->wp->meta[1] = ['d_book_title' => ['Dune'], 'd_book_api_key' => ['sk_live']];
        $box = $this->box('d', 'book', [$this->f->text('title'), $this->f->text('api_key')->sensitive()]);

        self::assertSame(['title' => 'Dune'], $box->values(1));
        self::assertSame('sk_live', $box->value(1, 'api_key'), 'readable by name');
    }

    public function test_invalid_values_are_not_saved_and_say_why(): void
    {
        $box = $this->box('d', 'book', [$this->f->email('contact')->label('Contact email')->required()]);

        self::assertSame(['Contact email must be a valid email address.'], $box->save(1, 'contact', 'nope'));
        self::assertSame(['Contact email is required.'], $box->save(1, 'contact', ''));
        self::assertArrayNotHasKey(1, $this->wp->meta);
    }

    public function test_every_value_of_a_multiple_field_is_validated(): void
    {
        $box = $this->box('d', 'book', [$this->f->select('tags', ['a' => 'A', 'b' => 'B'])->multiple()]);

        self::assertNotSame([], $box->save(1, 'tags', ['a', 'evil']));
        self::assertSame([], $box->save(1, 'tags', ['a', 'b']));
    }

    // --- Saving from the edit screen ---------------------------------------------------------

    public function test_a_save_writes_valid_fields_and_reports_invalid_ones(): void
    {
        $box = $this->box('d', 'book', [$this->f->text('title'), $this->f->email('contact')]);
        $_POST = [$box->nonceName() => 'n', 'd_book_title' => 'Dune', 'd_book_contact' => 'bad'];
        $box->handleSave(5, $this->post(5, 'book'));

        self::assertSame(['Contact must be a valid email address.'], $this->flash->get('metabox_errors_d_1'));
        self::assertSame(['Dune'], $this->wp->meta[5]['d_book_title']);
        self::assertArrayNotHasKey('d_book_contact', $this->wp->meta[5]);
    }

    public function test_blank_sensitive_field_keeps_the_stored_secret(): void
    {
        $this->wp->meta[5] = ['d_book_api_key' => ['sk_live']];
        $box = $this->box('d', 'book', [$this->f->text('api_key')->sensitive()]);
        $_POST = [$box->nonceName() => 'n', 'd_book_api_key' => ''];

        $box->handleSave(5, $this->post(5, 'book'));

        self::assertSame(['sk_live'], $this->wp->meta[5]['d_book_api_key']);
    }

    public function test_fields_missing_from_the_form_are_left_alone(): void
    {
        $this->wp->meta[5] = ['d_book_title' => ['Kept']];
        $box = $this->box('d', 'book', [$this->f->text('title')]);
        $_POST = [$box->nonceName() => 'n'];

        $box->handleSave(5, $this->post(5, 'book'));

        self::assertSame(['Kept'], $this->wp->meta[5]['d_book_title']);
    }

    /**
     * S2 for saving: nothing is written without a valid nonce, the right post type and edit_post.
     */
    public function test_saves_are_refused_without_nonce_capability_or_matching_post_type(): void
    {
        $box = $this->box('d', 'book', [$this->f->text('title')]);
        $_POST = [$box->nonceName() => 'n', 'd_book_title' => 'Hacked'];

        Functions\when('wp_verify_nonce')->justReturn(false);
        $box->handleSave(5, $this->post(5, 'book'));
        Functions\when('wp_verify_nonce')->justReturn(1);

        Functions\when('current_user_can')->justReturn(false);
        $box->handleSave(5, $this->post(5, 'book'));
        Functions\when('current_user_can')->justReturn(true);

        $box->handleSave(6, $this->post(6, 'page'));

        self::assertSame([], $this->wp->meta);
    }

    /**
     * S2 (Phase 4.7): 0.x registered a `wp_ajax_nopriv_` fetch for every meta box. 1.0 registers no
     * Ajax or REST endpoint at all — values reach quick edit inside the list table, for editors only.
     */
    public function test_anonymous_user_cannot_read_metabox_data(): void
    {
        $hooks = new \Codad5\WPToolkit\Foundation\HookRegistrar();
        $box = $this->box('e', 'event', [$this->f->date('start_date')->quickEdit(), $this->f->text('venue')]);

        $box->register($hooks);

        $names = array_column($hooks->all(), 'hook');
        self::assertSame([], array_values(array_filter($names, static fn (string $h): bool => str_starts_with($h, 'wp_ajax') || str_starts_with($h, 'rest_api'))));

        $this->wp->meta[5] = ['e_event_start_date' => ['2026-10-07']];
        Functions\when('current_user_can')->justReturn(false); // logged out
        ob_start();
        $box->printInlineData($this->post(5, 'event'));
        self::assertSame('', ob_get_clean());
    }

    /**
     * S2 (Phase 4.7): reads and writes both require edit_post on that post and the box's own post type.
     */
    public function test_metabox_data_requires_edit_post_and_matching_post_type(): void
    {
        $this->wp->meta[5] = ['e_event_start_date' => ['2026-10-07']];
        $box = $this->box('e', 'event', [$this->f->date('start_date')->quickEdit()]);
        $asked = [];
        Functions\when('current_user_can')->alias(static function (string $cap, ...$args) use (&$asked): bool {
            $asked[] = [$cap, ...$args];
            return true;
        });

        ob_start();
        $box->printInlineData($this->post(5, 'page'));   // wrong post type: nothing, not even a capability check
        $box->printInlineData($this->post(5, 'event'));
        $html = (string) ob_get_clean();

        self::assertSame([['edit_post', 5]], $asked);
        self::assertSame(1, substr_count($html, 'wptoolkit-quick-edit'));
    }

    public function test_render_escapes_values_and_prints_a_nonce(): void
    {
        $this->wp->meta[5] = ['d_book_title' => ['"><script>x</script>']];
        $box = $this->box('d', 'book', [$this->f->text('title')->description('<b>Shown</b> on the shop')]);
        Functions\expect('wp_nonce_field')->once()->with($box->nonceAction(), $box->nonceName());

        ob_start();
        $box->render($this->post(5, 'book'));
        $html = (string) ob_get_clean();

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>Shown', $html);
        self::assertStringContainsString('for="my-plugin-d-title"', $html);
        self::assertStringContainsString('aria-describedby="my-plugin-d-title-description"', $html);
        self::assertStringContainsString('id="my-plugin-d-title-description"', $html);
    }

    // --- Quick edit (no public endpoint, unlike 0.x) ----------------------------------------------

    public function test_quick_edit_values_ride_in_the_rows_inline_data_for_editors_only(): void
    {
        $this->wp->meta[5] = ['e_event_start_date' => ['2026-10-07'], 'e_event_featured' => ['on']];
        $box = $this->box('e', 'event', [
            $this->f->date('start_date')->quickEdit(),
            $this->f->checkbox('featured')->quickEdit(),
            $this->f->text('venue'),
        ]);

        ob_start();
        $box->printInlineData($this->post(5, 'event'));
        $html = (string) ob_get_clean();

        self::assertStringContainsString('data-key="e_event_start_date">2026-10-07</div>', $html);
        self::assertStringContainsString('data-key="e_event_featured">1</div>', $html);
        self::assertStringNotContainsString('venue', $html);

        Functions\when('current_user_can')->justReturn(false);
        ob_start();
        $box->printInlineData($this->post(5, 'event'));
        self::assertSame('', ob_get_clean());
    }

    public function test_quick_edit_renders_its_column_once_with_a_single_nonce(): void
    {
        $box = $this->box('e', 'event', [$this->f->date('start_date')->quickEdit()->required(), $this->f->date('end_date')->quickEdit()]);
        Functions\expect('wp_nonce_field')->once()->with($box->nonceAction(), $box->nonceName(), false);

        ob_start();
        $box->renderQuickEdit('e_event_start_date', 'event');
        $box->renderQuickEdit('e_event_end_date', 'event');
        $box->renderQuickEdit('title', 'event');
        $box->renderQuickEdit('e_event_start_date', 'page');
        $html = (string) ob_get_clean();

        self::assertSame(2, substr_count($html, '<input'));
        self::assertStringContainsString('name="e_event_start_date"', $html);
        self::assertStringNotContainsString('required', $html, 'a blank quick edit input must not block the row save');
    }

    public function test_fields_that_quick_edit_cannot_fill_are_refused_up_front(): void
    {
        $this->expectException(\Codad5\WPToolkit\Exceptions\InvalidConfigException::class);
        $this->expectExceptionMessage('Field "api_key" cannot be quick-edited');

        $this->box('e', 'event', [$this->f->text('api_key')->sensitive()->quickEdit()]);
    }

    /**
     * @param list<\Codad5\WPToolkit\Data\Field\Field> $fields
     */
    private function box(string $id, string $postType, array $fields): MetaBox
    {
        return new MetaBox($id, 'Details', $postType, $fields, new FieldTypes(), new Identity('my-plugin'), $this->flash);
    }

    private function post(int $id, string $type): WP_Post
    {
        return new WP_Post(['ID' => $id, 'post_type' => $type]);
    }
}
