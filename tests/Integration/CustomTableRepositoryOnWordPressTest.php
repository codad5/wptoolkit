<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Clock\SystemClock;
use Codad5\WPToolkit\Adapters\Repository\CustomTableRepository;
use Codad5\WPToolkit\Adapters\Repository\TableSchema;
use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Contract\RepositoryContract;
use Codad5\WPToolkit\Tests\Support\Entities\TableBook;
use PHPUnit\Framework\TestCase;

/**
 * CustomTableRepository on real MySQL: the same contract as the post type, so a Book moves between
 * them with one attribute change (Phase 4 DoD).
 */
final class CustomTableRepositoryOnWordPressTest extends TestCase
{
    use RepositoryContract;

    private Identity $identity;

    protected function setUp(): void
    {
        $this->identity = new Identity('wptk-it');
        TableSchema::install(TableBook::class, $this->identity);
    }

    protected function tearDown(): void
    {
        TableSchema::drop(TableBook::class, $this->identity);
    }

    protected function repository(): Repository
    {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . TableSchema::tableName(TableBook::class, $this->identity));

        return new CustomTableRepository(TableBook::class, new FieldTypes(), $this->identity, new ArrayStore(new SystemClock()));
    }

    protected function newBook(array $values = []): Entity
    {
        return new TableBook($values);
    }

    public function test_the_table_has_one_column_per_field(): void
    {
        global $wpdb;

        $table = TableSchema::tableName(TableBook::class, $this->identity);

        self::assertSame(['id', 'title', 'pages', 'format', 'featured', 'images', 'api_key'], $wpdb->get_col('DESCRIBE ' . $table));
        self::assertSame($wpdb->prefix . 'wptk_it_books', $table);
    }

    public function test_installing_twice_is_harmless(): void
    {
        $books = $this->repository();
        $books->save($this->newBook(['title' => 'Kept']));

        TableSchema::install(TableBook::class, $this->identity);

        self::assertSame(1, $books->count());
    }

    public function test_quotes_and_sql_in_values_are_data(): void
    {
        $books = $this->repository();
        $books->save($this->newBook(['title' => "Robert'); DROP TABLE books;--"]));

        self::assertSame(1, $books->count(Query::create()->where('title', 'like', "'); DROP")));
    }

    public function test_multiple_fields_cannot_be_queried(): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->repository()->query(Query::create()->where('images', 5));
    }
}
