<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Data;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Cache\CacheFactory;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Adapters\Repository\OptionsRepository;
use Codad5\WPToolkit\Adapters\Repository\PostTypeRepository;
use Codad5\WPToolkit\Adapters\Repository\RepositoryFactory;
use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\EntityRegistrar;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Config;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Support\Entities\MemoryBook;
use Codad5\WPToolkit\Tests\Support\Entities\OptionBook;
use Codad5\WPToolkit\Tests\TestCase;

final class EntityRegistrarTest extends TestCase
{
    private EntityRegistrar $registrar;

    private HookRegistrar $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = new HookRegistrar();
        $this->registrar = new EntityRegistrar($this->hooks, new FieldTypes(), new Identity('my-plugin'), new ArrayStore(new FrozenClock()));
    }

    public function test_an_entity_registers_its_post_type_taxonomy_and_a_matching_meta_box(): void
    {
        $registered = [];
        Functions\when('register_post_type')->alias(static function ($name, $args) use (&$registered) {
            $registered['post_type'] = [$name, $args['labels']['name']];
        });
        Functions\when('register_taxonomy')->alias(static function ($name, $postType) use (&$registered) {
            $registered['taxonomy'] = [$name, $postType];
        });

        $box = $this->registrar->entity(MemoryBook::class);
        foreach ($this->hooks->all() as $hook) {
            if ($hook['hook'] === 'init') {
                ($hook['callback'])();
            }
        }

        self::assertSame(['memory_book', 'Memory books'], $registered['post_type']);
        self::assertSame(['memory_genre', 'memory_book'], $registered['taxonomy']);
        self::assertNotNull($box);
        self::assertArrayNotHasKey('title', $box->fields(), 'post columns are edited by WordPress, not the meta box');
        self::assertSame('details_memory_book_pages', $box->metaKey('pages'), 'the keys PostTypeRepository reads');
    }

    public function test_bad_post_type_keys_are_explained(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('must be 1–20 lowercase letters');

        $this->registrar->entity(LongPostType::class);
    }

    public function test_the_same_meta_box_cannot_be_added_twice(): void
    {
        $f = new FieldFactory();
        $this->registrar->metaBox('extra', 'Extra', 'page', [$f->text('subtitle')]);

        $this->expectException(InvalidConfigException::class);
        $this->registrar->metaBox('extra', 'Extra', 'page', [$f->text('subtitle')]);
    }

    public function test_the_factory_picks_the_adapter_the_entity_declares(): void
    {
        $factory = new RepositoryFactory(new FieldTypes(), new Identity('my-plugin'), new CacheFactory(Config::fromArray('/p.php', ['slug' => 'my-plugin']), new Identity('my-plugin'), new FrozenClock()));

        self::assertInstanceOf(PostTypeRepository::class, $factory->for(MemoryBook::class));
        self::assertInstanceOf(OptionsRepository::class, $factory->for(OptionBook::class));
        self::assertSame($factory->for(MemoryBook::class), $factory->for(MemoryBook::class));

        $this->expectException(InvalidConfigException::class);
        $factory->for(NoStorage::class);
    }
}

#[PostType('a_post_type_name_that_is_too_long')]
final class LongPostType extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [];
    }
}

final class NoStorage extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [];
    }
}
