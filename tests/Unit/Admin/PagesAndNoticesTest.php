<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Admin\Notices;
use Codad5\WPToolkit\Admin\NoticeType;
use Codad5\WPToolkit\Admin\Page;
use Codad5\WPToolkit\Admin\Pages;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\TestCase;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;

final class PagesAndNoticesTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $userMeta = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->userMeta = [];
        Functions\when('get_user_meta')->alias(fn (int $id, string $key) => $this->userMeta[$id][$key] ?? '');
        Functions\when('update_user_meta')->alias(function (int $id, string $key, $value) {
            $this->userMeta[$id][$key] = $value;
            return true;
        });
        Functions\when('delete_user_meta')->alias(function (int $id, string $key) {
            unset($this->userMeta[$id][$key]);
            return true;
        });
        Functions\when('get_current_user_id')->justReturn(7);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_kses')->alias(static fn ($html) => strip_tags((string) $html, '<a><strong><em><code>'));
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    // --- Pages -----------------------------------------------------------------------------------

    public function test_pau_shaped_pages_register_parents_first_with_their_slugs_unchanged(): void
    {
        $calls = [];
        Functions\when('add_menu_page')->alias(static function (...$args) use (&$calls) {
            $calls[] = ['menu', $args[3], $args[2]];
            return 'toplevel_page_' . $args[3];
        });
        Functions\when('add_submenu_page')->alias(static function (...$args) use (&$calls) {
            $calls[] = ['sub', $args[4], $args[0]];
            return 'alumni_page_' . $args[4];
        });

        $pages = new Pages(new HookRegistrar());
        $pages->add(Page::under('pau-alumni-manager', 'pau-alumni-settings', 'Settings', 'manage_options'));
        $pages->add(Page::top('pau-alumni-manager', 'PAU Alumni Manager', 'manage_options')->icon('dashicons-groups')->position(30));
        $pages->add(Page::postTypeList('pau-alumni-manager', 'pau-executive', 'Executives', 'manage_options'));
        $pages->add(Page::hidden('pau-alumni-view', 'View alumnus', 'manage_options'));
        $pages->addToMenu();

        self::assertSame([
            ['menu', 'pau-alumni-manager', 'manage_options'],
            ['sub', 'pau-alumni-settings', 'pau-alumni-manager'],
            ['sub', 'edit.php?post_type=pau-executive', 'pau-alumni-manager'],
            ['sub', 'pau-alumni-view', ''],
        ], $calls);
        self::assertSame('toplevel_page_pau-alumni-manager', $pages->hookSuffix('pau-alumni-manager'));
    }

    public function test_urls_match_0x_admin_urls(): void
    {
        Functions\when('admin_url')->alias(static fn (string $path = '') => 'https://example.test/wp-admin/' . $path);
        Functions\when('add_query_arg')->alias(static fn (array $args, string $url) => $url . '&' . http_build_query($args));

        $pages = new Pages(new HookRegistrar());
        $pages->add(Page::hidden('pau-alumni-view', 'View', 'manage_options'));
        $pages->add(Page::postTypeList('x', 'pau-partner', 'Partners', 'edit_posts'));

        self::assertSame('https://example.test/wp-admin/admin.php?page=pau-alumni-view&id=4', $pages->url('pau-alumni-view', ['id' => 4]));
        self::assertSame('https://example.test/wp-admin/edit.php?post_type=pau-partner', $pages->url('edit.php?post_type=pau-partner'));
    }

    public function test_a_page_rechecks_its_capability_before_rendering(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        Functions\expect('wp_die')->once()->andThrow(new \RuntimeException('died'));
        $rendered = false;
        $page = Page::top('p', 'P', 'manage_options')->render(static function () use (&$rendered): void {
            $rendered = true;
        });

        try {
            (new Pages(new HookRegistrar()))->display($page);
        } catch (\RuntimeException) {
        }

        self::assertFalse($rendered);
    }

    public function test_a_page_can_render_a_view_with_lazy_data(): void
    {
        Functions\when('locate_template')->justReturn('');
        $renderer = new PhpTemplateRenderer(new TemplateLocator([__DIR__ . '/../../Fixtures/views'], null));
        $page = Page::top('p', 'P', 'read')->view('admin/greeting', static fn (): array => ['name' => '<Ada>']);

        ob_start();
        (new Pages(new HookRegistrar(), $renderer))->display($page);

        self::assertStringContainsString('<h1>&lt;Ada&gt;</h1>', (string) ob_get_clean());
    }

    public function test_pages_need_a_capability_and_a_clean_slug(): void
    {
        foreach ([static fn () => Page::top('p', 'P', ''), static fn () => Page::top('My Page', 'P', 'read')] as $i => $bad) {
            try {
                $bad();
                self::fail('case ' . $i);
            } catch (InvalidConfigException) {
                self::addToAssertionCount(1);
            }
        }
    }

    // --- Notices ---------------------------------------------------------------------------------

    public function test_a_flash_notice_shows_once_for_its_user_with_the_right_role(): void
    {
        $notices = $this->notices();
        $notices->flash('Saved <script>x</script><strong>now</strong>.');
        $notices->flash('It broke.', NoticeType::Error);

        $first = $this->printed($notices);
        self::assertStringContainsString('class="notice notice-success" role="status"', $first);
        self::assertStringContainsString('class="notice notice-error" role="alert"', $first);
        self::assertStringContainsString('<strong>now</strong>', $first);
        self::assertStringNotContainsString('<script>', $first);

        self::assertSame('', $this->printed($notices), 'shown once');
    }

    public function test_a_persistent_notice_stays_until_that_user_dismisses_it(): void
    {
        $notices = $this->notices();
        $notices->persistent('api-missing', 'Add your API key.', NoticeType::Warning);

        self::assertStringContainsString('data-wptoolkit-notice="api-missing" data-owner="my-plugin"', $this->printed($notices));

        Functions\when('check_ajax_referer')->justReturn(1);
        $_POST = ['id' => 'api-missing'];
        $response = $this->captureJson(static fn () => $notices->handleDismiss());

        self::assertTrue($response->success);
        self::assertSame('', $this->printed($notices));
    }

    public function test_only_declared_dismissible_notices_can_be_dismissed(): void
    {
        $notices = $this->notices();
        $notices->persistent('sticky', 'Read me.', dismissible: false);
        Functions\when('check_ajax_referer')->justReturn(1);

        foreach (['sticky', 'made-up'] as $id) {
            $_POST = ['id' => $id];
            self::assertFalse($this->captureJson(static fn () => $notices->handleDismiss())->success, $id);
        }
        self::assertSame([], $this->userMeta);
    }

    public function test_persistent_notices_respect_their_capability(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        $notices = $this->notices();
        $notices->persistent('admins-only', 'Hi admins.');

        self::assertSame('', $this->printed($notices));
    }

    private function notices(): Notices
    {
        return new Notices(new Identity('my-plugin'), new HookRegistrar());
    }

    private function printed(Notices $notices): string
    {
        ob_start();
        $notices->print();

        return (string) ob_get_clean();
    }
}
