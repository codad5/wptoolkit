<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\DB;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Codad5\WPToolkit\Tests\Fixtures\Models\MembersOnlyModel;
use Codad5\WPToolkit\Tests\Fixtures\Models\PrivateFormModel;
use Codad5\WPToolkit\Tests\Fixtures\Models\PublicBookModel;
use Codad5\WPToolkit\Tests\TestCase;
use Codad5\WPToolkit\Utils\Config;
use RuntimeException;

/**
 * S1: Model registered its search and autocomplete Ajax endpoints for
 * logged-out users on every post type, with no capability check, a
 * client-chosen limit, and client-chosen meta in the results.
 */
final class ModelAjaxSearchTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubs(['sanitize_textarea_field' => static fn ($v) => (string) $v]);
        Functions\when('check_ajax_referer')->justReturn(1);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('current_user_can')->justReturn(false);
        $this->config = new Config(['slug' => 'test-plugin']);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    // --- Registration -------------------------------------------------------

    public function test_public_post_type_keeps_its_public_search_endpoints(): void
    {
        Actions\expectAdded('wp_ajax_nopriv_book_search')->once();
        Actions\expectAdded('wp_ajax_nopriv_book_autocomplete')->once();

        $this->model(PublicBookModel::class)->register_hooks();
    }

    public function test_non_public_post_type_gets_no_logged_out_search_endpoints(): void
    {
        Actions\expectAdded('wp_ajax_contact_form_search')->once();
        Actions\expectAdded('wp_ajax_nopriv_contact_form_search')->never();
        Actions\expectAdded('wp_ajax_nopriv_contact_form_autocomplete')->never();

        $this->model(PrivateFormModel::class)->register_hooks();
    }

    public function test_model_requiring_authentication_gets_no_logged_out_search_endpoints(): void
    {
        Actions\expectAdded('wp_ajax_nopriv_member_doc_search')->never();
        Actions\expectAdded('wp_ajax_nopriv_member_doc_autocomplete')->never();

        $this->model(MembersOnlyModel::class)->register_hooks();
    }

    // --- Handler: who may search --------------------------------------------

    public function test_anonymous_user_cannot_search_a_model_requiring_authentication(): void
    {
        $model = $this->model(MembersOnlyModel::class);
        $_POST = ['search' => 'minutes'];

        $response = $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertFalse($response->success);
        self::assertSame(401, $response->status);
        self::assertSame([], $model->searches);
    }

    public function test_logged_in_user_without_view_capability_cannot_search_a_members_only_model(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        $model = $this->model(MembersOnlyModel::class);
        $_POST = ['search' => 'minutes'];

        $response = $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertSame(403, $response->status);
        self::assertSame([], $model->searches);
    }

    public function test_anonymous_user_cannot_search_a_non_public_post_type(): void
    {
        $model = $this->model(PrivateFormModel::class);
        $_POST = ['search' => 'john@example.com'];

        $response = $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertFalse($response->success);
        self::assertSame(403, $response->status);
        self::assertSame([], $model->searches);
    }

    // --- Handler: what an anonymous search may ask for ----------------------

    public function test_anonymous_search_is_limited_to_published_posts_and_a_capped_page_size(): void
    {
        $model = $this->model(PublicBookModel::class);
        $_POST = ['search' => 'dune', 'limit' => '-1'];

        $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertCount(1, $model->searches);
        $args = $model->searches[0]['args'];
        self::assertSame('publish', $args['post_status']);
        self::assertGreaterThanOrEqual(1, $args['posts_per_page']);
        self::assertLessThanOrEqual(50, $args['posts_per_page']);
    }

    public function test_anonymous_search_never_returns_or_searches_meta(): void
    {
        $model = $this->model(PublicBookModel::class);
        $_POST = ['search' => 'dune', 'include_meta' => 'true', 'fields' => ['title', 'meta']];

        $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertFalse($model->searches[0]['config']['include_meta']);
        self::assertNotContains('meta', $model->searches[0]['fields']);
    }

    public function test_unknown_search_fields_are_dropped(): void
    {
        $model = $this->model(PublicBookModel::class);
        $_POST = ['search' => 'dune', 'fields' => ['title', 'post_password']];

        $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertSame(['title'], $model->searches[0]['fields']);
    }

    public function test_editor_may_include_meta(): void
    {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('current_user_can')->alias(static fn ($cap) => $cap === 'edit_posts');
        $model = $this->model(PublicBookModel::class);
        $_POST = ['search' => 'dune', 'include_meta' => 'true', 'fields' => ['title', 'meta']];

        $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertTrue($model->searches[0]['config']['include_meta']);
        self::assertContains('meta', $model->searches[0]['fields']);
        self::assertArrayNotHasKey('post_status', $model->searches[0]['args']);
    }

    // --- S4: errors -----------------------------------------------------------

    public function test_search_failure_does_not_leak_the_exception_message(): void
    {
        Functions\expect('error_log')->once();
        $model = $this->model(PublicBookModel::class);
        $model->search_throws = new RuntimeException('SQLSTATE[42S02] wp_secret_table missing');
        $_POST = ['search' => 'dune'];

        $response = $this->captureJson(fn () => $model->handle_ajax_search());

        self::assertFalse($response->success);
        self::assertSame(500, $response->status);
        self::assertStringNotContainsString('SQLSTATE', $response->json());
        self::assertStringNotContainsString('wp_secret_table', $response->json());
    }

    // --- Autocomplete ---------------------------------------------------------

    public function test_autocomplete_limit_is_clamped_to_a_sane_range(): void
    {
        $model = $this->model(PublicBookModel::class);
        $_POST = ['term' => 'du', 'limit' => '-20'];

        $this->captureJson(fn () => $model->handle_ajax_autocomplete());

        self::assertGreaterThanOrEqual(1, $model->autocompletes[0]['limit']);
        self::assertLessThanOrEqual(50, $model->autocompletes[0]['limit']);
    }

    public function test_anonymous_user_cannot_autocomplete_a_model_requiring_authentication(): void
    {
        $model = $this->model(MembersOnlyModel::class);
        $_POST = ['term' => 'minutes'];

        $response = $this->captureJson(fn () => $model->handle_ajax_autocomplete());

        self::assertSame(401, $response->status);
        self::assertSame([], $model->autocompletes);
    }

    /**
     * @template T of PublicBookModel|PrivateFormModel|MembersOnlyModel
     * @param class-string<T> $class
     * @return T
     */
    private function model(string $class): object
    {
        $model = $class::get_instance($this->config);
        $model->reset_recorders();
        return $model;
    }
}
