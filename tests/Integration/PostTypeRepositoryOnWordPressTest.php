<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Repository\PostTypeRepository;
use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Contract\RepositoryContract;
use Codad5\WPToolkit\Tests\Support\Entities\PostBook;
use PHPUnit\Framework\TestCase;

/**
 * PostTypeRepository on a real WordPress: WP_Query, meta_query, tax_query and the post caches.
 */
final class PostTypeRepositoryOnWordPressTest extends TestCase
{
    use RepositoryContract;

    protected function setUp(): void
    {
        if (!post_type_exists('wptk_book')) {
            register_post_type('wptk_book', ['public' => false]);
        }
        if (!taxonomy_exists('wptk_genre')) {
            register_taxonomy('wptk_genre', 'wptk_book');
        }
    }

    protected function tearDown(): void
    {
        $this->clear();
    }

    protected function repository(): Repository
    {
        $this->clear();

        return new PostTypeRepository(PostBook::class, new FieldTypes(), new Identity('wptk-it'));
    }

    protected function newBook(array $values = []): Entity
    {
        return new PostBook($values);
    }

    protected function supportsTerms(): bool
    {
        return true;
    }

    public function test_fields_use_the_post_columns_and_metabox_keys(): void
    {
        $books = $this->repository();
        $book = $books->save($this->newBook(['title' => 'Dune', 'pages' => 412, 'images' => [5, 6]]));
        $id = (int) $book->id();

        self::assertSame('Dune', get_post($id)?->post_title);
        self::assertSame('412', get_post_meta($id, 'details_wptk_book_pages', true));
        self::assertSame(['5', '6'], get_post_meta($id, 'details_wptk_book_images', false), 'one row per value, like 0.x');
        self::assertSame('publish', get_post_status($id));
    }

    public function test_values_written_by_0x_are_read(): void
    {
        $books = $this->repository();
        $id = wp_insert_post(['post_type' => 'wptk_book', 'post_title' => 'Old', 'post_status' => 'publish']);
        update_post_meta($id, 'details_wptk_book_featured', 'on');
        add_post_meta($id, 'details_wptk_book_images', '9');
        add_post_meta($id, 'details_wptk_book_images', '10');

        $book = $books->find($id);

        self::assertNotNull($book);
        self::assertTrue($book->get('featured'));
        self::assertSame([9, 10], $book->get('images'));
    }

    public function test_backslashes_and_quotes_survive_the_round_trip(): void
    {
        $books = $this->repository();
        $title = "O'Brien \\ \"Notes\"";
        $book = $books->save($this->newBook(['title' => $title, 'api_key' => 'a\\b']));

        $found = $books->find((int) $book->id());

        self::assertSame($title, $found?->get('title'));
        self::assertSame('a\\b', $found?->get('api_key'));
    }

    public function test_other_post_types_are_invisible(): void
    {
        $books = $this->repository();
        $page = wp_insert_post(['post_type' => 'page', 'post_title' => 'Not a book', 'post_status' => 'publish']);

        self::assertNull($books->find($page));
        self::assertFalse($books->delete($page));
        self::assertNotNull(get_post($page));
        wp_delete_post($page, true);
    }

    public function test_trash_is_excluded_from_queries(): void
    {
        $books = $this->repository();
        $book = $books->save($this->newBook(['title' => 'Trashed']));
        wp_trash_post((int) $book->id());

        self::assertSame(0, $books->count());
        self::assertSame([], $books->query(Query::create()));
    }

    private function clear(): void
    {
        $ids = get_posts(['post_type' => 'wptk_book', 'post_status' => ['any', 'trash'], 'numberposts' => -1, 'fields' => 'ids']);
        foreach ($ids as $id) {
            wp_delete_post((int) $id, true);
        }
    }
}
