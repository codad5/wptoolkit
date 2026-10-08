<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Data;

use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Data\ValidationException;

/**
 * Loads and stores entities of one class (ADR-0009). Adapters: PostTypeRepository,
 * CustomTableRepository, OptionsRepository and ArrayRepository (in memory, for tests). Every
 * adapter passes the same contract tests, so switching storage changes no domain code.
 *
 * @template T of Entity
 */
interface Repository
{
    /**
     * @return T|null
     */
    public function find(int $id): ?Entity;

    /**
     * @param list<int> $ids
     * @return list<T> In the order of `$ids`; missing IDs are skipped.
     */
    public function findMany(array $ids): array;

    /**
     * @return list<T>
     */
    public function query(?Query $query = null): array;

    /**
     * Matching records across all pages (ignores page and page size).
     */
    public function count(?Query $query = null): int;

    /**
     * Validate, then insert or update. Sets the entity's id on insert.
     *
     * @param T $entity
     * @return T
     * @throws ValidationException When a field is invalid; nothing is written.
     */
    public function save(Entity $entity): Entity;

    /**
     * @param T|int $entity
     * @return bool Whether a record was deleted.
     */
    public function delete(Entity|int $entity): bool;
}
