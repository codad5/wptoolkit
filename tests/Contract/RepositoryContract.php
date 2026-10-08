<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Contract;

use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Data\ValidationException;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * The behaviour every Repository adapter must have (Phase 4.3, ADR-0009). A trait rather than a
 * base class so the same rules run as unit tests (in memory, options) and as integration tests on
 * real WordPress (post type, custom table). Entities use Tests\Support\Entities\BookFields.
 */
trait RepositoryContract
{
    /**
     * An empty repository of a BookFields entity.
     *
     * @return Repository<Entity>
     */
    abstract protected function repository(): Repository;

    /**
     * @param array<string, mixed> $values
     */
    abstract protected function newBook(array $values = []): Entity;

    protected function supportsTerms(): bool
    {
        return false;
    }

    public function test_save_inserts_and_find_reads_the_same_values_back(): void
    {
        $books = $this->repository();
        $book = $this->newBook(['title' => 'Dune', 'pages' => '412', 'featured' => true, 'images' => ['7', '8'], 'api_key' => 'sk']);

        $saved = $books->save($book);

        self::assertSame($book, $saved);
        self::assertNotNull($book->id());
        self::assertFalse($book->isDirty());

        $found = $books->find((int) $book->id());
        self::assertNotNull($found);
        self::assertSame('Dune', $found->get('title'));
        self::assertSame(412, $found->get('pages'));
        self::assertSame('paperback', $found->get('format'), 'default applies when nothing was set');
        self::assertTrue($found->get('featured'));
        self::assertSame([7, 8], $found->get('images'));
        self::assertSame('sk', $found->get('api_key'));
        self::assertArrayNotHasKey('api_key', $found->toArray(), 'sensitive fields stay out of bulk reads');
    }

    public function test_empty_values_read_back_as_defaults(): void
    {
        $books = $this->repository();
        $book = $books->save($this->newBook(['title' => 'Blank', 'featured' => false]));

        $found = $books->find((int) $book->id());

        self::assertNotNull($found);
        self::assertNull($found->get('pages'));
        self::assertFalse($found->get('featured'));
        self::assertSame([], $found->get('images'));
    }

    public function test_save_updates_in_place(): void
    {
        $books = $this->repository();
        $book = $books->save($this->newBook(['title' => 'Dune', 'pages' => 412, 'images' => [1, 2]]));
        $id = $book->id();

        $book->set('title', 'Dune Messiah')->set('images', [3])->set('pages', null);
        $books->save($book);

        $found = $books->find((int) $id);
        self::assertSame($id, $book->id());
        self::assertNotNull($found);
        self::assertSame('Dune Messiah', $found->get('title'));
        self::assertSame([3], $found->get('images'));
        self::assertNull($found->get('pages'));
        self::assertSame(1, $books->count());
    }

    public function test_find_misses_and_find_many_keeps_order(): void
    {
        $books = $this->repository();
        $a = $books->save($this->newBook(['title' => 'A']));
        $b = $books->save($this->newBook(['title' => 'B']));

        self::assertNull($books->find(999999));
        self::assertSame(['B', 'A'], array_map(
            static fn (Entity $e) => $e->get('title'),
            $books->findMany([(int) $b->id(), 999999, (int) $a->id()])
        ));
    }

    public function test_delete_removes_once(): void
    {
        $books = $this->repository();
        $book = $books->save($this->newBook(['title' => 'Gone']));
        $id = (int) $book->id();

        self::assertTrue($books->delete($book));
        self::assertNull($books->find($id));
        self::assertFalse($books->delete($id));
        self::assertFalse($book->exists());
    }

    public function test_invalid_entities_are_not_written(): void
    {
        $books = $this->repository();

        try {
            $books->save($this->newBook(['title' => '', 'format' => 'scroll']));
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame(['title', 'format'], array_keys($e->errors()));
        }

        self::assertSame(0, $books->count());
    }

    public function test_where_compares_numbers_as_numbers_and_strings_case_insensitively(): void
    {
        $books = $this->seeded();

        self::assertSame(['Children of Dune', 'Dune'], $this->titles($books, Query::create()->where('pages', '>=', 300)->orderBy('title')));
        self::assertSame(['Emma'], $this->titles($books, Query::create()->where('pages', '<', 100)));
        self::assertSame(['Dune'], $this->titles($books, Query::create()->where('title', 'dune')));
        self::assertSame(['Children of Dune', 'Dune'], $this->titles($books, Query::create()->where('title', 'like', 'DUNE')->orderBy('title')));
        self::assertSame(['Emma'], $this->titles($books, Query::create()->where('format', 'hardcover')));
        self::assertSame(['Children of Dune', 'Dune', 'Persuasion'], $this->titles($books, Query::create()->where('format', '!=', 'hardcover')->where('pages', '>', 100)->orderBy('title')));
    }

    public function test_where_in_and_checkbox_values(): void
    {
        $books = $this->seeded();

        self::assertSame(['Dune', 'Emma'], $this->titles($books, Query::create()->whereIn('title', ['Emma', 'Dune', 'Missing'])->orderBy('title')));
        self::assertSame(['Children of Dune', 'Persuasion'], $this->titles($books, Query::create()->where('title', 'not in', ['Emma', 'Dune'])->orderBy('title')));
        self::assertSame(['Dune'], $this->titles($books, Query::create()->where('featured', true)));
        self::assertSame(['Children of Dune', 'Emma', 'Persuasion'], $this->titles($books, Query::create()->where('featured', false)->orderBy('title')));
    }

    public function test_ordering_paging_and_counting(): void
    {
        $books = $this->seeded();
        $byPages = Query::create()->orderBy('pages', 'DESC')->perPage(2);

        self::assertSame(['Children of Dune', 'Dune'], $this->titles($books, $byPages));
        self::assertSame(['Persuasion', 'Emma'], $this->titles($books, $byPages->page(2)));
        self::assertSame([], $this->titles($books, $byPages->page(3)));
        self::assertSame(4, $books->count($byPages->page(2)), 'count ignores paging');
        self::assertSame(2, $books->count(Query::create()->where('pages', '>', 250)));
    }

    public function test_queries_name_real_fields_only_and_never_sensitive_ones(): void
    {
        $books = $this->repository();

        try {
            $books->query(Query::create()->where('author', 'Herbert'));
            self::fail('Unknown field should be refused.');
        } catch (InvalidConfigException) {
        }

        $this->expectException(InvalidConfigException::class);
        $books->query(Query::create()->where('api_key', 'sk'));
    }

    public function test_terms_are_saved_loaded_and_queried(): void
    {
        if (!$this->supportsTerms()) {
            self::markTestSkipped('This adapter stores no terms.');
        }

        $books = $this->repository();
        $taxonomy = $this->newBook()->definition()->taxonomyNames()[0];
        $dune = $books->save($this->newBook(['title' => 'Dune'])->setTerms($taxonomy, ['sci-fi', 'classic']));
        $books->save($this->newBook(['title' => 'Emma'])->setTerms($taxonomy, ['romance']));

        $found = $books->find((int) $dune->id());
        self::assertNotNull($found);
        $terms = $found->terms($taxonomy);
        sort($terms);
        self::assertSame(['classic', 'sci-fi'], $terms);
        self::assertSame(['Dune'], $this->titles($books, Query::create()->whereTerm($taxonomy, ['sci-fi', 'horror'])));

        $found->set('pages', 10);
        $books->save($found);
        self::assertCount(2, $books->find((int) $dune->id())?->terms($taxonomy) ?? [], 'saving fields leaves terms alone');
    }

    /**
     * @return Repository<Entity>
     */
    private function seeded(): Repository
    {
        $books = $this->repository();
        foreach (
            [
                ['title' => 'Dune', 'pages' => 412, 'featured' => true],
                ['title' => 'Emma', 'pages' => 90, 'format' => 'hardcover'],
                ['title' => 'Persuasion', 'pages' => 249],
                ['title' => 'Children of Dune', 'pages' => 444],
            ] as $values
        ) {
            $books->save($this->newBook($values + ['featured' => false]));
        }

        return $books;
    }

    /**
     * @param Repository<Entity> $books
     * @return list<mixed>
     */
    private function titles(Repository $books, Query $query): array
    {
        return array_map(static fn (Entity $e) => $e->get('title'), $books->query($query));
    }
}
