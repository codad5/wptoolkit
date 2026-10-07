<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Query\Condition;
use Codad5\WPToolkit\Data\Query\Operator;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\RepositoryException;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Entities in their own table (#[Table]; create it with TableSchema). For data that outgrows post
 * meta. Every statement goes through `$wpdb->prepare()`; column names come from the entity's fields,
 * never from input. Rows are cached by id in the CacheStore and dropped on save and delete — writes
 * that bypass this repository must call forget().
 *
 * Multiple fields are stored as JSON and cannot be used in `where`.
 *
 * @template T of Entity
 * @extends BaseRepository<T>
 */
final class CustomTableRepository extends BaseRepository
{
    private readonly string $table;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(string $entityClass, FieldTypes $types, Identity $identity, private readonly CacheStore $cache)
    {
        parent::__construct($entityClass, $types);
        $this->table = TableSchema::tableName($entityClass, $identity);
    }

    public function find(int $id): ?Entity
    {
        $row = $this->cache->remember($this->cacheKey($id), null, function () use ($id) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name from the identity; cached by this repository.
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);

            return is_array($row) ? $row : false;
        });

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function query(?Query $query = null): array
    {
        global $wpdb;

        $query ??= Query::create();
        [$where, $params] = $this->whereSql($query);
        $params[] = $query->limit();
        $params[] = $query->offset();

        $sql = "SELECT * FROM {$this->table}{$where}{$this->orderSql($query)} LIMIT %d OFFSET %d";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- identifiers from the entity definition; every value is a placeholder.
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        $entities = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $this->cache->set($this->cacheKey((int) $row['id']), $row);
                $entities[] = $this->hydrate($row);
            }
        }

        return $entities;
    }

    public function count(?Query $query = null): int
    {
        global $wpdb;

        [$where, $params] = $this->whereSql($query ?? Query::create());
        $sql = "SELECT COUNT(*) FROM {$this->table}{$where}";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- prepared when there are values; identifiers from the entity definition.
        return (int) $wpdb->get_var($params === [] ? $sql : $wpdb->prepare($sql, ...$params));
    }

    public function save(Entity $entity): Entity
    {
        global $wpdb;

        $this->assertValid($entity);

        $data = [];
        $formats = [];
        $names = $entity->exists() ? array_keys($entity->dirty()) : array_keys($this->definition->fields);
        foreach ($names as $name) {
            $field = $this->definition->fields[$name];
            $stored = $this->toStorage($field, $entity->get($name));
            if ($field->isMultiple()) {
                $stored = $stored === [] ? null : (string) wp_json_encode($stored);
            }
            $data[TableSchema::column($name)] = $stored;
            $formats[] = is_int($stored) ? '%d' : (is_float($stored) ? '%f' : '%s');
        }

        $id = $entity->id();
        if ($id === null) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- this is the storage adapter.
            $ok = $wpdb->insert($this->table, $data, $formats);
            $id = (int) $wpdb->insert_id;
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- this is the storage adapter.
            $ok = $data === [] ? 1 : $wpdb->update($this->table, $data, ['id' => $id], $formats, ['%d']);
        }
        if ($ok === false || $id === 0) {
            throw RepositoryException::writeFailed($this->definition->class, $wpdb->last_error !== '' ? $wpdb->last_error : 'the query failed');
        }

        $this->forget($id);
        $fresh = $this->find($id);
        if ($fresh !== null) {
            $values = [];
            foreach (array_keys($this->definition->fields) as $name) {
                $values[$name] = $fresh->get($name);
            }
            $entity->markPersisted($id, $values);
        }

        return $entity;
    }

    public function delete(Entity|int $entity): bool
    {
        global $wpdb;

        $id = $entity instanceof Entity ? $entity->id() : $entity;
        if ($id === null) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- this is the storage adapter.
        $deleted = (int) $wpdb->delete($this->table, ['id' => $id], ['%d']) > 0;
        $this->forget($id);
        if ($deleted && $entity instanceof Entity) {
            $entity->markDeleted();
        }

        return $deleted;
    }

    /**
     * Drop a cached row after writing to the table some other way.
     */
    public function forget(int $id): void
    {
        $this->cache->delete($this->cacheKey($id));
    }

    /**
     * @param array<string, mixed> $row
     * @return T
     */
    private function hydrate(array $row): Entity
    {
        $values = [];
        foreach ($this->definition->fields as $name => $field) {
            $stored = $row[TableSchema::column($name)] ?? null;
            if ($field->isMultiple() && is_string($stored)) {
                $decoded = json_decode($stored, true);
                $stored = is_array($decoded) && $decoded !== [] ? $decoded : null;
            }
            $values[$name] = $this->fromStorage($field, $stored);
        }

        $entity = $this->definition->newEntity();
        $entity->markPersisted((int) $row['id'], $values);

        return $entity;
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function whereSql(Query $query): array
    {
        global $wpdb;

        $this->assertQueryable($query);
        if ($query->terms() !== []) {
            throw new InvalidConfigException('Custom-table entities have no terms to query.');
        }

        $clauses = [];
        $params = [];
        foreach ($query->conditions() as $condition) {
            [$sql, $values] = $this->conditionSql($condition, $wpdb);
            $clauses[] = $sql;
            array_push($params, ...$values);
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function conditionSql(Condition $condition, \wpdb $wpdb): array
    {
        if ($condition->field !== 'id' && $this->definition->field($condition->field)->isMultiple()) {
            throw new InvalidConfigException(sprintf('"%s" is a multiple field; CustomTableRepository cannot query it.', $condition->field));
        }

        $column = $condition->field === 'id' ? 'id' : TableSchema::column($condition->field);
        $value = $this->conditionToStorage($condition);
        $placeholder = static fn ($v): string => is_int($v) ? '%d' : (is_float($v) ? '%f' : '%s');

        if ($condition->operator->takesList()) {
            $list = array_values(array_filter((array) $value, static fn ($v) => $v !== null));
            if ($list === []) {
                return [$condition->operator === Operator::In ? '1=0' : '1=1', []];
            }
            $in = implode(', ', array_map($placeholder, $list));

            return $condition->operator === Operator::In
                ? ["{$column} IN ({$in})", $list]
                : ["({$column} NOT IN ({$in}) OR {$column} IS NULL)", $list];
        }

        if ($condition->operator === Operator::Like) {
            return ["{$column} LIKE %s", ['%' . $wpdb->esc_like(is_scalar($value) ? (string) $value : '') . '%']];
        }

        if ($value === null) {
            return match ($condition->operator) {
                Operator::Equals => ["{$column} IS NULL", []],
                Operator::NotEquals => ["{$column} IS NOT NULL", []],
                default => throw new InvalidConfigException(sprintf('"%s" cannot compare against null.', $condition->operator->value)),
            };
        }

        $operator = $condition->operator->value;
        $sql = "{$column} {$operator} {$placeholder($value)}";

        return [$condition->operator === Operator::NotEquals ? "({$sql} OR {$column} IS NULL)" : $sql, [$value]];
    }

    private function orderSql(Query $query): string
    {
        $parts = [];
        foreach ($query->order() as [$field, $direction]) {
            $parts[] = ($field === 'id' ? 'id' : TableSchema::column($field)) . ' ' . $direction;
        }
        $parts[] = 'id ASC';

        return ' ORDER BY ' . implode(', ', array_unique($parts));
    }

    private function cacheKey(int $id): string
    {
        return $this->table . ':' . $id;
    }
}
