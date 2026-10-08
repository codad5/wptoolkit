<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Frontend\PublicPages;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;
use PHPUnit\Framework\TestCase;

/**
 * Public pages against WordPress's real rewrite system: the rules land in the stored rule set, a
 * pretty URL resolves to our query vars, and the flush happens once per change.
 */
final class PublicPagesOnWordPressTest extends TestCase
{
    private string $previousStructure = '';

    protected function setUp(): void
    {
        global $wp_rewrite;
        $this->previousStructure = (string) get_option('permalink_structure');
        $wp_rewrite->set_permalink_structure('/%postname%/');
        delete_option('wptk-pp_rewrite_hash');
    }

    protected function tearDown(): void
    {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure($this->previousStructure);
        delete_option('wptk-pp_rewrite_hash');
        flush_rewrite_rules(false);
    }

    public function test_a_pretty_url_resolves_to_the_pages_query_vars(): void
    {
        $pages = $this->pages();
        $pages->page('library/books/{id}', 'Book', 'admin/greeting')->public();
        add_filter('query_vars', [$pages, 'queryVars']);
        $pages->addRewriteRules();

        $rules = get_option('rewrite_rules');
        self::assertIsArray($rules);
        self::assertSame('index.php?wptk_pp_page=library-books-id&wptk_pp_p_id=$matches[1]', $rules['^library/books/([^/]+)/?$'] ?? null);

        global $wp;
        $_SERVER['REQUEST_URI'] = '/library/books/42/';
        $wp->parse_request();
        self::assertSame('library-books-id', $wp->query_vars['wptk_pp_page'] ?? null);
        self::assertSame('42', $wp->query_vars['wptk_pp_p_id'] ?? null);

        self::assertSame(md5((string) wp_json_encode(['^library/books/([^/]+)/?$'])), get_option('wptk-pp_rewrite_hash'));
        remove_filter('query_vars', [$pages, 'queryVars']);
    }

    private function pages(): PublicPages
    {
        $identity = new Identity('wptk-pp');

        return new PublicPages(
            $identity,
            new HookRegistrar(),
            new Dispatcher(new Container(), $identity, new ArrayLogger()),
            new PhpTemplateRenderer(new TemplateLocator([dirname(__DIR__) . '/Fixtures/views'], null))
        );
    }
}
