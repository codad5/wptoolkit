<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldTypes;

/**
 * Keeps entities in memory for the length of the request. For consumers' unit tests: it passes the
 * same contract tests as the WordPress adapters, so code tested against it behaves the same live.
 *
 *     $books = new ArrayRepository(Book::class);
 *
 * @template T of Entity
 * @extends StateRepository<T>
 */
final class ArrayRepository extends StateRepository
{
    /** @var array{next: int, items: array<int, array{fields: array<string, mixed>, terms: array<string, list<int|string>>}>} */
    private array $state = ['next' => 1, 'items' => []];

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(string $entityClass, ?FieldTypes $types = null)
    {
        parent::__construct($entityClass, $types ?? new FieldTypes());
    }

    protected function load(): array
    {
        return $this->state;
    }

    protected function persist(array $state): void
    {
        $this->state = $state;
    }
}
