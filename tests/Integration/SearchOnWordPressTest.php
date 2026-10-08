<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Repository\PostTypeRepository;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Search\Search;
use Codad5\WPToolkit\Data\Search\SearchResult;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Support\Entities\SearchBook;
use Codad5\WPToolkit\Tests\Support\Entities\SecretBook;
use PHPUnit\Framework\TestCase;

/**
 * Data\Search on a real WordPress: the C1 fix (OR across sources) and the S1 security scenarios
 * carried over from 0.x (Phase 4.7).
 */
final class SearchOnWordPressTest extends TestCase
{
    private Identity $identity;

    /** @var PostTypeRepository<SearchBook> */
    private PostTypeRepository $books;

    protected function setUp(): void
    {
        register_post_type('wptk_search', ['public' => true]);
        register_post_type('wptk_secret', ['public' => false]);
        register_taxonomy('wptk_topic', 'wptk_search');

        $this->identity = new Identity('wptk-it');
        $this->books = new PostTypeRepository(SearchBook::class, new FieldTypes(), $this->identity);
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        foreach (get_posts(['post_type' => ['wptk_search', 'wptk_secret'], 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $id) {
            wp_delete_post((int) $id, true);
        }
        foreach (get_terms(['taxonomy' => 'wptk_topic', 'hide_empty' => false, 'fields' => 'ids']) as $term) {
            wp_delete_term((int) $term, 'wptk_topic');
        }
    }

    /**
     * C1: 0.x ANDed the title search with the meta query, so these matched nothing.
     */
    public function test_a_term_matches_title_or_meta_or_taxonomy(): void
    {
        $this->book(['title' => 'Foo Fighters']);
        $this->book(['title' => 'Unrelated', 'isbn' => 'FOO-123']);
        $this->book(['title' => 'Tagged'], topics: ['Foolproof']);
        $this->book(['title' => 'Nothing here', 'isbn' => '999']);

        $titles = $this->titles($this->search()->run('foo'));
        sort($titles);

        self::assertSame(['Foo Fighters', 'Tagged', 'Unrelated'], $titles);
    }

    public function test_title_matches_rank_above_meta_matches(): void
    {
        $this->book(['title' => 'Notes', 'isbn' => 'dune-1']);
        $this->book(['title' => 'Dune']);

        self::assertSame(['Dune', 'Notes'], $this->titles($this->search()->run('dune')));
    }

    public function test_public_search_returns_only_published_posts(): void
    {
        $this->book(['title' => 'Foo published']);
        $this->book(['title' => 'Foo draft'], status: 'draft');
        $this->book(['title' => 'Foo private'], status: 'private');

        self::assertSame(['Foo published'], $this->titles($this->search()->run('foo')));

        wp_set_current_user($this->admin());
        $all = $this->titles($this->search()->run('foo'));
        sort($all);
        self::assertSame(['Foo draft', 'Foo private', 'Foo published'], $all, 'editors see what they may read');
    }

    public function test_non_public_post_type_is_not_searchable_by_logged_out_users(): void
    {
        $secrets = new PostTypeRepository(SecretBook::class, new FieldTypes(), $this->identity);
        wp_insert_post(['post_type' => 'wptk_secret', 'post_title' => 'Foo secret', 'post_status' => 'publish']);
        $search = (new Search(SecretBook::class, $secrets, $this->identity))->in('title');

        self::assertSame(0, $search->run('foo')->total);

        wp_set_current_user($this->admin());
        self::assertSame(1, $search->run('foo')->total);
    }

    public function test_public_search_never_returns_or_searches_meta(): void
    {
        $this->book(['title' => 'Plain', 'notes' => 'foo in a field nobody allowed', 'secret' => 'foo-key']);
        $this->book(['title' => 'Foo', 'notes' => 'private notes', 'isbn' => 'X1', 'secret' => 'sk_live']);

        $result = $this->search()->run('foo')->toArray();

        self::assertSame(['Foo'], array_column($result['items'], 'title'), 'notes is not in the allow-list');
        self::assertSame(['id', 'title', 'score', 'isbn'], array_keys($result['items'][0]), 'only exposed fields leave');

        $this->expectException(InvalidConfigException::class);
        $this->search()->in('secret');
    }

    public function test_search_page_size_is_capped(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->book(['title' => 'Foo ' . $i]);
        }

        $result = $this->search()->run('foo', page: 1, perPage: 100000);

        self::assertSame(100, $result->perPage);
        self::assertSame(3, $result->total);
        self::assertCount(1, $this->search()->run('foo', page: 2, perPage: 2)->items);
        self::assertSame([], $this->search()->run('f')->items, 'one character is not a search');
    }

    /**
     * @return Search<SearchBook>
     */
    private function search(): Search
    {
        return (new Search(SearchBook::class, $this->books, $this->identity))->in('title', 'content', 'isbn', 'wptk_topic')->expose('isbn');
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $topics
     */
    private function book(array $values, string $status = 'publish', array $topics = []): void
    {
        $book = new SearchBook($values);
        if ($topics !== []) {
            $book->setTerms('wptk_topic', $topics);
        }
        $this->books->save($book);
        if ($status !== 'publish') {
            wp_update_post(['ID' => (int) $book->id(), 'post_status' => $status]);
        }
    }

    /**
     * @return list<string>
     */
    private function titles(SearchResult $result): array
    {
        return array_map(static fn (array $item): string => $item['title'], $result->items);
    }

    private function admin(): int
    {
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);

        return (int) ($admins[0] ?? 1);
    }
}
