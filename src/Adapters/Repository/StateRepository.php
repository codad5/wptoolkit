<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Query\Query;

/**
 * Adapters that keep every record in one array — in memory or in one option. Records hold stored
 * values (what a field type's sanitize() returns), exactly as a database row would.
 *
 * @template T of Entity
 * @extends BaseRepository<T>
 * @phpstan-type State array{next: int, items: array<int, array{fields: array<string, mixed>, terms: array<string, list<int|string>>}>}
 */
abstract class StateRepository extends BaseRepository
{
    /**
     * @return State
     */
    abstract protected function load(): array;

    /**
     * @param State $state
     */
    abstract protected function persist(array $state): void;

    public function find(int $id): ?Entity
    {
        $record = $this->load()['items'][$id] ?? null;

        return $record === null ? null : $this->hydrate($id, $record);
    }

    public function query(?Query $query = null): array
    {
        $state = $this->load();
        [$ids] = $this->evaluate($query ?? Query::create(), $this->rows($state, $query ?? Query::create()));

        return array_map(fn (int $id) => $this->hydrate($id, $state['items'][$id]), $ids);
    }

    public function count(?Query $query = null): int
    {
        return $this->evaluate($query ?? Query::create(), $this->rows($this->load(), $query ?? Query::create()))[1];
    }

    public function save(Entity $entity): Entity
    {
        $this->assertValid($entity);

        $state = $this->load();
        $id = $entity->id();
        if ($id === null || !isset($state['items'][$id])) {
            $id = $id ?? $state['next'];
            $state['next'] = max($state['next'], $id + 1);
        }

        $fields = [];
        foreach ($this->definition->fields as $name => $field) {
            $fields[$name] = $this->toStorage($field, $entity->get($name));
        }
        $terms = $state['items'][$id]['terms'] ?? [];
        foreach ($entity->changedTaxonomies() as $taxonomy) {
            $terms[$taxonomy] = $entity->terms($taxonomy);
        }

        $state['items'][$id] = ['fields' => $fields, 'terms' => $terms];
        $this->persist($state);

        $entity->markPersisted($id, $this->readFields($fields), $terms);

        return $entity;
    }

    public function delete(Entity|int $entity): bool
    {
        $id = $entity instanceof Entity ? $entity->id() : $entity;
        $state = $this->load();
        if ($id === null || !isset($state['items'][$id])) {
            return false;
        }

        unset($state['items'][$id]);
        $this->persist($state);
        if ($entity instanceof Entity) {
            $entity->markDeleted();
        }

        return true;
    }

    /**
     * @param array{fields: array<string, mixed>, terms: array<string, list<int|string>>} $record
     * @return T
     */
    private function hydrate(int $id, array $record): Entity
    {
        $entity = $this->definition->newEntity();
        $entity->markPersisted($id, $this->readFields($record['fields']), $record['terms']);

        return $entity;
    }

    /**
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function readFields(array $stored): array
    {
        $values = [];
        foreach ($this->definition->fields as $name => $field) {
            $values[$name] = $this->fromStorage($field, $field->isMultiple() && ($stored[$name] ?? []) === [] ? null : ($stored[$name] ?? null));
        }

        return $values;
    }

    /**
     * Code-value rows for the in-memory evaluator, after the term filter.
     *
     * @param State $state
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $state, Query $query): array
    {
        $rows = [];
        foreach ($state['items'] as $id => $record) {
            foreach ($query->terms() as $taxonomy => $wanted) {
                $has = array_map('strval', $record['terms'][$taxonomy] ?? []);
                if (array_intersect($has, array_map('strval', $wanted)) === []) {
                    continue 2;
                }
            }
            $rows[$id] = ['id' => $id] + $this->readFields($record['fields']);
        }

        return $rows;
    }
}
