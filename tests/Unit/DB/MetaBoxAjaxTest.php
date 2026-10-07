<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\DB;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Codad5\WPToolkit\DB\MetaBox;
use Codad5\WPToolkit\Tests\TestCase;
use WP_Post;

/**
 * S2: the metabox "fetch data" Ajax endpoint returned any post's meta to
 * anonymous users, guarded only by a nonce.
 */
final class MetaBoxAjaxTest extends TestCase
{
    private const POST_ID = 42;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('check_ajax_referer')->justReturn(1);
        $_POST = ['post_id' => (string) self::POST_ID, 'nonce' => 'n'];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    public function test_fetch_data_endpoint_is_not_registered_for_logged_out_users(): void
    {
        Actions\expectAdded('wp_ajax_wptoolkit_metabox_details_fetch_data')->once();
        Actions\expectAdded('wp_ajax_nopriv_wptoolkit_metabox_details_fetch_data')->never();

        MetaBox::create('details', 'Details', 'book')->setup_actions();
    }

    public function test_user_who_cannot_edit_the_post_gets_403_and_no_meta(): void
    {
        Functions\when('get_post')->justReturn(new WP_Post(['ID' => self::POST_ID, 'post_type' => 'book']));
        Functions\expect('current_user_can')->with('edit_post', self::POST_ID)->andReturn(false);
        Functions\expect('get_post_meta')->never();

        $response = $this->captureJson(fn () => MetaBox::create('details', 'Details', 'book')->handle_ajax());

        self::assertFalse($response->success);
        self::assertSame(403, $response->status);
    }

    public function test_post_of_another_post_type_is_rejected_even_for_editors(): void
    {
        Functions\when('get_post')->justReturn(new WP_Post(['ID' => self::POST_ID, 'post_type' => 'page']));
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('get_post_meta')->never();

        $response = $this->captureJson(fn () => MetaBox::create('details', 'Details', 'book')->handle_ajax());

        self::assertFalse($response->success);
        self::assertSame(404, $response->status);
    }

    public function test_editor_of_a_matching_post_gets_its_meta(): void
    {
        Functions\when('get_post')->justReturn(new WP_Post(['ID' => self::POST_ID, 'post_type' => 'book']));
        Functions\expect('current_user_can')->with('edit_post', self::POST_ID)->andReturn(true);
        Functions\when('get_post_meta')->justReturn(['details_book_isbn' => ['123']]);

        $response = $this->captureJson(fn () => MetaBox::create('details', 'Details', 'book')->handle_ajax());

        self::assertTrue($response->success);
        self::assertSame(200, $response->status);
    }
}
