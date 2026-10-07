<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Data;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Attributes\Table;
use Codad5\WPToolkit\Data\Attributes\Taxonomy;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\EntityDefinition;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Tests\Support\Entities\MemoryBook;
use Codad5\WPToolkit\Tests\TestCase;

final class EntityTest extends TestCase
{
    public function test_values_are_read_and_written_by_field_name(): void
    {
        $book = new MemoryBook(['title' => 'Dune']);
        $book->pages = 412;

        self::assertSame('Dune', $book->title);
        self::assertSame(412, $book->get('pages'));
        self::assertSame('paperback', $book->format, 'defaults fill unset fields');
        self::assertTrue(isset($book->title));
        self::assertFalse(isset($book->nope));
    }

    public function test_unknown_fields_are_refused(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('has no field "author"');

        new MemoryBook(['author' => 'Herbert']);
    }

    public function test_dirty_tracking_starts_after_persisting(): void
    {
        $book = new MemoryBook(['title' => 'Dune']);
        self::assertTrue($book->isDirty('title'));

        $book->markPersisted(7, ['title' => 'Dune']);
        self::assertFalse($book->isDirty());
        self::assertSame(7, $book->id());

        $book->title = 'Dune';
        self::assertFalse($book->isDirty(), 'same value is not a change');
        $book->title = 'Dune Messiah';
        self::assertSame(['title' => 'Dune Messiah'], $book->dirty());

        $book->setTerms('memory_genre', ['sci-fi']);
        self::assertSame(['memory_genre'], $book->changedTaxonomies());
    }

    public function test_to_array_leaves_out_sensitive_fields(): void
    {
        $array = (new MemoryBook(['title' => 'Dune', 'api_key' => 'sk']))->toArray();

        self::assertArrayNotHasKey('api_key', $array);
        self::assertSame(['id', 'title', 'pages', 'format', 'featured', 'images'], array_keys($array));
    }

    public function test_terms_need_a_declared_taxonomy(): void
    {
        $this->expectException(InvalidConfigException::class);

        (new MemoryBook())->setTerms('category', [1]);
    }

    public function test_definitions_read_attributes_once(): void
    {
        $definition = EntityDefinition::of(MemoryBook::class);

        self::assertSame($definition, EntityDefinition::of(MemoryBook::class));
        self::assertInstanceOf(PostType::class, $definition->storage);
        self::assertSame(['memory_genre'], $definition->taxonomyNames());
    }

    public function test_definitions_refuse_conflicting_or_reserved_declarations(): void
    {
        foreach ([TwoStorages::class => 'more than one storage', TaxonomyWithoutPostType::class => 'needs #[PostType]', IdField::class => '"id" is reserved'] as $class => $message) {
            try {
                EntityDefinition::of($class);
                self::fail($class);
            } catch (InvalidConfigException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function test_post_columns_are_opt_in_and_validated(): void
    {
        $mapped = new PostType('book', columns: ['title' => 'post_title']);
        $plain = new PostType('pau-executive');

        self::assertSame('post_title', $mapped->columnFor('title'));
        self::assertNull($plain->columnFor('title'), 'a 0.x meta field called "title" stays meta');

        $this->expectException(InvalidConfigException::class);
        new PostType('book', columns: ['title' => 'post_password']);
    }

    public function test_query_page_size_is_capped_and_queries_are_immutable(): void
    {
        $base = Query::create();
        $huge = $base->perPage(100000)->page(3);

        self::assertSame(Query::MAX_PER_PAGE, $huge->limit());
        self::assertSame(200, $huge->offset());
        self::assertSame(Query::DEFAULT_PER_PAGE, $base->limit());
        self::assertSame(1, $base->perPage(-5)->limit());
        self::assertSame([['id', 'ASC']], $base->order());
    }

    public function test_query_rejects_malformed_conditions(): void
    {
        foreach ([
            static fn () => Query::create()->where('pages', 'between', 1),
            static fn () => Query::create()->where('pages', 'in', 5),
            static fn () => Query::create()->where('pages', '=', [1, 2]),
            static fn () => Query::create()->orderBy('pages', 'sideways'),
        ] as $i => $bad) {
            try {
                $bad();
                self::fail('case ' . $i);
            } catch (InvalidConfigException) {
                self::addToAssertionCount(1);
            }
        }
    }
}

#[PostType('two')]
#[Table('two')]
final class TwoStorages extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [];
    }
}

#[Taxonomy('loose')]
final class TaxonomyWithoutPostType extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [];
    }
}

final class IdField extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [$f->number('id')];
    }
}
