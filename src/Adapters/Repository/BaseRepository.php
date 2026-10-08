<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\EntityDefinition;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Field\FieldValidator;
use Codad5\WPToolkit\Data\Query\Condition;
use Codad5\WPToolkit\Data\Query\Operator;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Data\ValidationException;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Support\Validation\Rules;

/**
 * What every adapter shares: validation, conversion between code values and stored values through
 * each field's type, and checking that queries only name real fields.
 *
 * @template T of Entity
 * @implements Repository<T>
 */
abstract class BaseRepository implements Repository
{
    /** @var EntityDefinition<T> */
    protected readonly EntityDefinition $definition;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(string $entityClass, protected readonly FieldTypes $types)
    {
        $this->definition = EntityDefinition::of($entityClass);
    }

    public function findMany(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            $entity = $this->find($id);
            if ($entity !== null) {
                $found[] = $entity;
            }
        }

        return $found;
    }

    /**
     * @param T $entity
     * @throws ValidationException
     */
    protected function assertValid(Entity $entity): void
    {
        $values = [];
        foreach ($this->definition->fields as $name => $field) {
            $values[$name] = $entity->get($name);
        }

        $errors = (new FieldValidator($this->types))->validateAll($this->definition->fields, $values);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Code value → stored value: a list of sanitized values for multiple fields, otherwise one
     * sanitized value or null for "nothing stored".
     */
    protected function toStorage(Field $field, mixed $value): mixed
    {
        $type = $this->types->get($field->type);

        if ($field->isMultiple()) {
            $stored = [];
            foreach (is_array($value) ? $value : [$value] as $item) {
                $clean = Rules::isEmpty($item) ? null : $type->sanitize($item, $field);
                if ($clean !== null && $clean !== '') {
                    $stored[] = $clean;
                }
            }

            return $stored;
        }

        if (Rules::isEmpty($value)) {
            return null;
        }

        $clean = $type->sanitize($value, $field);

        return $clean === '' ? null : $clean;
    }

    /**
     * Stored value → code value. `$stored` is a list for multiple fields; null means nothing stored.
     */
    protected function fromStorage(Field $field, mixed $stored): mixed
    {
        if ($stored === null) {
            return $field->defaultValue();
        }

        $type = $this->types->get($field->type);
        if ($field->isMultiple()) {
            $values = [];
            foreach (is_array($stored) ? $stored : [$stored] as $item) {
                $read = $type->read($item, $field);
                if ($read !== null) {
                    $values[] = $read;
                }
            }

            return $values;
        }

        return $type->read($stored, $field);
    }

    /**
     * A condition's value as it is stored (for adapters that compare in storage).
     */
    protected function conditionToStorage(Condition $condition): mixed
    {
        if ($condition->field === 'id') {
            return $condition->operator->takesList() ? array_map('intval', (array) $condition->value) : (int) $condition->value;
        }

        $field = $this->definition->field($condition->field)->multiple(false);
        if ($condition->operator === Operator::Like) {
            return (string) (is_scalar($condition->value) ? $condition->value : '');
        }
        if ($condition->operator->takesList()) {
            return array_map(fn ($v) => $this->toStorage($field, $v), (array) $condition->value);
        }

        return $this->toStorage($field, $condition->value);
    }

    /**
     * @throws InvalidConfigException When a query names a field the entity doesn't have.
     */
    protected function assertQueryable(Query $query): void
    {
        foreach ($query->conditions() as $condition) {
            $this->assertField($condition->field);
            if ($condition->field !== 'id' && $this->definition->field($condition->field)->isSensitive()) {
                throw new InvalidConfigException(sprintf('Field "%s" is sensitive and cannot be queried (ADR-0021).', $condition->field));
            }
        }
        foreach ($query->order() as [$field]) {
            $this->assertField($field);
        }
        foreach (array_keys($query->terms()) as $taxonomy) {
            if (!in_array($taxonomy, $this->definition->taxonomyNames(), true)) {
                throw new InvalidConfigException(sprintf('%s declares no #[Taxonomy(\'%s\')].', $this->definition->class, $taxonomy));
            }
        }
    }

    private function assertField(string $name): void
    {
        if ($name !== 'id' && !$this->definition->hasField($name)) {
            throw new InvalidConfigException(sprintf('%s has no field "%s" to query.', $this->definition->class, $name));
        }
    }

    /**
     * Filter, sort and slice code-value rows in memory with the same meaning SQL and WP_Query give
     * the other adapters (string comparison is case-insensitive, like MySQL's default collation).
     *
     * @param array<int, array<string, mixed>> $rows id => code values (with 'id')
     * @return array{list<int>, int} The page's ids and the total match count.
     */
    protected function evaluate(Query $query, array $rows): array
    {
        $this->assertQueryable($query);

        $matches = array_filter($rows, function (array $row) use ($query): bool {
            foreach ($query->conditions() as $condition) {
                if (!$this->matches($row[$condition->field] ?? null, $condition)) {
                    return false;
                }
            }

            return true;
        });

        $order = $query->order();
        uksort($matches, function (int $a, int $b) use ($matches, $order): int {
            foreach ($order as [$field, $direction]) {
                $result = self::compare($matches[$a][$field] ?? null, $matches[$b][$field] ?? null);
                if ($result !== 0) {
                    return $direction === 'DESC' ? -$result : $result;
                }
            }

            return $a <=> $b;
        });

        return [array_slice(array_keys($matches), $query->offset(), $query->limit()), count($matches)];
    }

    private function matches(mixed $actual, Condition $condition): bool
    {
        $expected = $this->conditionValue($condition);
        $candidates = is_array($actual) ? $actual : [$actual];
        if (is_array($actual) && $actual === []) {
            $candidates = [null];
        }

        $any = function (callable $test) use ($candidates): bool {
            foreach ($candidates as $candidate) {
                if ($test($candidate)) {
                    return true;
                }
            }
            return false;
        };

        return match ($condition->operator) {
            Operator::Equals => $any(static fn ($v) => self::compare($v, $expected) === 0),
            Operator::NotEquals => !$any(static fn ($v) => self::compare($v, $expected) === 0),
            Operator::GreaterThan => $any(static fn ($v) => $v !== null && self::compare($v, $expected) > 0),
            Operator::GreaterOrEqual => $any(static fn ($v) => $v !== null && self::compare($v, $expected) >= 0),
            Operator::LessThan => $any(static fn ($v) => $v !== null && self::compare($v, $expected) < 0),
            Operator::LessOrEqual => $any(static fn ($v) => $v !== null && self::compare($v, $expected) <= 0),
            Operator::In => $any(static fn ($v) => self::inList($v, (array) $expected)),
            Operator::NotIn => !$any(static fn ($v) => self::inList($v, (array) $expected)),
            Operator::Like => $any(static fn ($v) => is_scalar($v) && is_scalar($expected) && stripos((string) $v, (string) $expected) !== false),
        };
    }

    /**
     * The condition's value as code reads it, so it compares like stored-and-read data.
     */
    private function conditionValue(Condition $condition): mixed
    {
        if ($condition->field === 'id' || $condition->operator === Operator::Like) {
            return $this->conditionToStorage($condition);
        }

        $field = $this->definition->field($condition->field)->multiple(false);
        $stored = $this->conditionToStorage($condition);

        return $condition->operator->takesList()
            ? array_map(fn ($v) => $v === null ? null : $this->fromStorage($field, $v), (array) $stored)
            : ($stored === null ? null : $this->fromStorage($field, $stored));
    }

    /**
     * @param array<mixed> $list
     */
    private static function inList(mixed $value, array $list): bool
    {
        foreach ($list as $item) {
            if (self::compare($value, $item) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if ($a === null || $b === null) {
            return ($b === null) <=> ($a === null); // null sorts first, as in MySQL
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a <=> (bool) $b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        return strcasecmp(is_scalar($a) ? (string) $a : '', is_scalar($b) ? (string) $b : '') <=> 0;
    }
}
