<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Adapters\Cache\CacheFactory;
use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Attributes\OptionStorage;
use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Attributes\Table;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\EntityDefinition;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Gives each entity the repository its storage attribute asks for (Factory). One instance per
 * entity class per application.
 *
 *     $books = $repositories->for(Book::class);   // PostTypeRepository<Book>
 */
final class RepositoryFactory
{
    /** @var array<class-string<Entity>, Repository<covariant Entity>> */
    private array $made = [];

    public function __construct(
        private readonly FieldTypes $types,
        private readonly Identity $identity,
        private readonly CacheFactory $caches
    ) {
    }

    /**
     * @template T of Entity
     * @param class-string<T> $entityClass
     * @return Repository<T>
     */
    public function for(string $entityClass): Repository
    {
        if (!isset($this->made[$entityClass])) {
            $storage = EntityDefinition::of($entityClass)->storage;
            $this->made[$entityClass] = match (true) {
                $storage instanceof PostType => new PostTypeRepository($entityClass, $this->types, $this->identity),
                // Rows are cached per request (or in a persistent object cache) — never in transients.
                $storage instanceof Table => new CustomTableRepository(
                    $entityClass,
                    $this->types,
                    $this->identity,
                    $this->caches->make('repository', 'object')
                ),
                $storage instanceof OptionStorage => new OptionsRepository($entityClass, $this->types, $this->identity),
                default => throw new InvalidConfigException(sprintf(
                    '%s declares no storage. Add #[PostType], #[Table] or #[OptionStorage], or use ArrayRepository in tests.',
                    $entityClass
                )),
            };
        }

        /** @var Repository<T> */
        return $this->made[$entityClass];
    }
}
