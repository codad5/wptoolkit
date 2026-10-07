<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Data;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Repository\ArrayRepository;
use Codad5\WPToolkit\Data\Search\Scorers;
use Codad5\WPToolkit\Data\Search\Search;
use Codad5\WPToolkit\Data\Search\SearchResult;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Support\Entities\OptionBook;
use Codad5\WPToolkit\Tests\Support\Entities\SearchBook;
use Codad5\WPToolkit\Tests\TestCase;
use WP_Post;

final class SearchTest extends TestCase
{
    public function test_the_title_scorer_ranks_exact_then_prefix_then_contains(): void
    {
        $scorer = Scorers::title();
        $book = new SearchBook();

        $exact = $scorer->score('Dune', $this->post('dune'), $book);
        $prefix = $scorer->score('Dune', $this->post('Dune Messiah'), $book);
        $contains = $scorer->score('Dune', $this->post('Children of Dune'), $book);

        self::assertGreaterThan($prefix, $exact);
        self::assertGreaterThan($contains, $prefix);
        self::assertSame(0.0, $scorer->score('Dune', $this->post('Emma'), $book));
    }

    public function test_the_field_scorer_counts_only_the_fields_it_was_given(): void
    {
        $book = new SearchBook(['isbn' => 'DUNE-1', 'notes' => 'dune dune']);

        self::assertSame(10.0, Scorers::fields(['isbn'])->score('dune', $this->post('x'), $book));
        self::assertSame(20.0, Scorers::fields(['isbn', 'notes'])->score('dune', $this->post('x'), $book));
    }

    public function test_only_post_columns_fields_and_declared_taxonomies_can_be_searched(): void
    {
        $search = $this->search();
        $search->in('title', 'isbn', 'wptk_topic');

        foreach (['secret' => 'sensitive', 'category' => 'not a post column, field or taxonomy'] as $source => $message) {
            try {
                $search->in($source);
                self::fail($source);
            } catch (InvalidConfigException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }

        $this->expectException(InvalidConfigException::class);
        $search->expose('secret');
    }

    public function test_search_needs_a_post_type_entity(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Search(OptionBook::class, new ArrayRepository(OptionBook::class), new Identity('my-plugin'));
    }

    public function test_results_expose_only_allow_listed_fields(): void
    {
        $book = new SearchBook(['isbn' => '1', 'notes' => 'private', 'secret' => 'sk']);
        $book->markPersisted(4, ['isbn' => '1', 'notes' => 'private', 'secret' => 'sk']);

        $array = (new SearchResult([['entity' => $book, 'title' => 'T', 'score' => 1.0]], 41, 2, 20, ['isbn']))->toArray();

        self::assertSame([['id' => 4, 'title' => 'T', 'score' => 1.0, 'isbn' => '1']], $array['items']);
        self::assertSame(3, $array['pages']);
    }

    public function test_short_terms_and_unknown_post_types_search_nothing(): void
    {
        Functions\when('get_post_type_object')->justReturn(null);
        Functions\stubs(['wp_strip_all_tags' => static fn ($v) => strip_tags((string) $v)]);

        self::assertSame(0, $this->search()->in('title')->run('dune')->total);
        self::assertSame([], $this->search()->in('title')->run('d')->items);
    }

    /**
     * @return Search<SearchBook>
     */
    private function search(): Search
    {
        return new Search(SearchBook::class, new ArrayRepository(SearchBook::class), new Identity('my-plugin'));
    }

    private function post(string $title): WP_Post
    {
        Functions\stubs(['wp_strip_all_tags' => static fn ($v) => strip_tags((string) $v)]);

        return new WP_Post(['ID' => 1, 'post_title' => $title, 'post_content' => '', 'post_excerpt' => '']);
    }
}
