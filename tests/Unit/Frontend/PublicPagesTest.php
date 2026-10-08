<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Frontend;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Frontend\PublicPages;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\HttpError;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Tests\TestCase;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;

final class PublicPagesTest extends TestCase
{
    /** @var array<string, string> */
    private array $queryVars = [];

    /** @var list<string> */
    private array $events = [];

    private bool $exited = false;

    private ArrayLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryVars = [];
        $this->events = [];
        $this->exited = false;
        Functions\when('get_query_var')->alias(fn (string $var) => $this->queryVars[$var] ?? '');
        Functions\when('get_current_user_id')->justReturn(0);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('locate_template')->justReturn('');
        Functions\when('wp_is_block_theme')->justReturn(false);
        Functions\when('status_header')->alias(function (int $code) {
            $this->events[] = 'status:' . $code;
        });
        Functions\when('get_header')->alias(function () {
            echo '[header]';
        });
        Functions\when('get_footer')->alias(function () {
            echo '[footer]';
        });
    }

    public function test_paths_become_rewrite_rules_with_prefixed_query_vars_flushed_only_on_change(): void
    {
        $rules = [];
        Functions\when('add_rewrite_rule')->alias(static function (string $regex, string $query) use (&$rules) {
            $rules[$regex] = $query;
        });
        $flushes = 0;
        Functions\when('flush_rewrite_rules')->alias(static function () use (&$flushes) {
            $flushes++;
        });
        $stored = [];
        Functions\when('get_option')->alias(static function (string $name) use (&$stored) {
            return $stored[$name] ?? false;
        });
        Functions\when('update_option')->alias(static function (string $name, $value) use (&$stored) {
            $stored[$name] = $value;
            return true;
        });

        $pages = $this->pages();
        $pages->page('library', 'Library', 'admin/greeting')->public();
        $pages->page('library/books/{id}', 'Book', 'admin/greeting')->public();
        $pages->addRewriteRules();
        $pages->addRewriteRules();

        self::assertSame([
            '^library/?$' => 'index.php?my_plugin_page=library',
            '^library/books/([^/]+)/?$' => 'index.php?my_plugin_page=library-books-id&my_plugin_p_id=$matches[1]',
        ], $rules);
        self::assertSame(1, $flushes, 'flushed when the rules changed, not on every request');
        self::assertSame(['q', 'my_plugin_page', 'my_plugin_p_id'], $pages->queryVars(['q']));
    }

    public function test_a_page_renders_its_view_inside_the_theme_with_its_title(): void
    {
        $pages = $this->pages();
        $pages->page('library/books/{id}', static fn (Request $r): string => 'Book ' . $r->input('id'), 'admin/greeting', static fn (Request $r): array => ['name' => 'Book #' . $r->input('id')])
            ->public()
            ->args(['id' => ['rules' => 'required|integer', 'type' => 'int']]);
        $this->visit('library-books-id', ['my_plugin_p_id' => '42']);
        Functions\when('get_bloginfo')->justReturn('My Site');
        Functions\when('wp_die')->alias(function ($message) {
            throw new \RuntimeException('wp_die: ' . $message . ' | ' . implode(' / ', $this->logger->messages()));
        });
        Functions\when('apply_filters')->returnArg(2);

        ob_start();
        $pages->serve();
        $html = (string) ob_get_clean();

        self::assertSame('[header]<h1>Book #42</h1>[footer]', trim(str_replace("\n", '', $html)));
        self::assertContains('status:200', $this->events);
        self::assertTrue($this->exited);
        self::assertSame('Book 42 – My Site', $pages->documentTitle('Home'));
        self::assertSame(['my-plugin-page', 'my-plugin-page-library-books-id'], $pages->bodyClass([]));
    }

    public function test_a_missing_record_shows_the_themes_404(): void
    {
        $pages = $this->pages();
        $pages->page('library/books/{id}', 'Book', 'admin/greeting', static fn (): array => throw HttpError::notFound())->public();
        $this->visit('library-books-id', ['my_plugin_p_id' => '999']);
        $GLOBALS['wp_query'] = new class {
            public bool $is404 = false;

            public function set_404(): void
            {
                $this->is404 = true;
            }
        };
        Functions\when('nocache_headers')->justReturn(null);

        $pages->serve();

        self::assertTrue($GLOBALS['wp_query']->is404);
        self::assertContains('status:404', $this->events);
        self::assertFalse($this->exited, 'WordPress goes on to load 404.php');
        unset($GLOBALS['wp_query']);
    }

    public function test_logged_out_visitors_are_sent_to_log_in(): void
    {
        $pages = $this->pages();
        $pages->page('account/orders', 'Orders', 'admin/greeting')->loggedIn();
        $this->visit('account-orders');
        Functions\expect('auth_redirect')->once();

        $pages->serve();

        self::assertTrue($this->exited);
    }

    public function test_a_page_without_an_access_rule_is_refused(): void
    {
        $pages = $this->pages();
        $pages->page('secret', 'Secret', 'admin/greeting');
        $this->visit('secret');
        Functions\expect('wp_die')->once()->with(\Mockery::any(), '', ['response' => 403])->andThrow(new \RuntimeException('died'));

        $this->expectExceptionMessage('died'); // wp_die() never returns
        $pages->serve();
    }

    public function test_invalid_parameters_never_reach_the_view(): void
    {
        $reached = false;
        $pages = $this->pages();
        $pages->page('library/books/{id}', 'Book', 'admin/greeting', static function () use (&$reached): array {
            $reached = true;
            return [];
        })->public()->args(['id' => ['rules' => 'required|integer']]);
        $this->visit('library-books-id', ['my_plugin_p_id' => 'drop-table']);
        Functions\expect('wp_die')->once()->with(\Mockery::any(), '', ['response' => 422])->andThrow(new \RuntimeException('died'));

        try {
            $pages->serve();
        } catch (\RuntimeException) {
            // wp_die() never returns
        }

        self::assertFalse($reached);
    }

    public function test_other_requests_are_left_to_wordpress(): void
    {
        $pages = $this->pages();
        $pages->page('library', 'Library', 'admin/greeting')->public();

        $pages->serve();

        self::assertSame([], $this->events);
        self::assertSame('https://x.test/old', $pages->keepOurUrls('https://x.test/old'));
        $this->visit('library');
        self::assertFalse($pages->keepOurUrls('https://x.test/'), 'WordPress must not "correct" our URL to the home page');
    }

    public function test_urls_and_bad_paths(): void
    {
        Functions\when('home_url')->alias(static fn (string $path) => 'https://x.test/' . $path);
        Functions\when('user_trailingslashit')->alias(static fn (string $p) => $p . '/');
        $pages = $this->pages();

        self::assertSame('https://x.test/library/books/42/', $pages->url('library/books/{id}', ['id' => 42]));

        $this->expectException(InvalidConfigException::class);
        $pages->page('Library?x=1', 'Bad', 'v');
    }

    /**
     * @param array<string, string> $params
     */
    private function visit(string $key, array $params = []): void
    {
        $this->queryVars = ['my_plugin_page' => $key] + $params;
    }

    private function pages(): PublicPages
    {
        $identity = new Identity('my-plugin');

        return new PublicPages(
            $identity,
            new HookRegistrar(),
            new Dispatcher(new Container(), $identity, $this->logger = new ArrayLogger()),
            new PhpTemplateRenderer(new TemplateLocator([__DIR__ . '/../../Fixtures/views'], null)),
            exit: function (): void {
                $this->exited = true;
            }
        );
    }
}
