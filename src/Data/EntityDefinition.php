<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data;

use Codad5\WPToolkit\Data\Attributes\OptionStorage;
use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Attributes\Table;
use Codad5\WPToolkit\Data\Attributes\Taxonomy;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use ReflectionClass;

/**
 * What an entity class declares — its fields, its storage attribute and its taxonomies — read
 * once per class.
 *
 * @template T of Entity
 */
final class EntityDefinition
{
    /** @var array<class-string<Entity>, self<covariant Entity>> */
    private static array $cache = [];

    /**
     * @param class-string<T> $class
     * @param array<string, Field> $fields
     * @param list<Taxonomy> $taxonomies
     */
    private function __construct(
        public readonly string $class,
        public readonly array $fields,
        public readonly PostType|Table|OptionStorage|null $storage,
        public readonly array $taxonomies
    ) {
    }

    /**
     * @template E of Entity
     * @param class-string<E> $class
     * @return self<E>
     */
    public static function of(string $class): self
    {
        if (isset(self::$cache[$class])) {
            /** @var self<E> */
            return self::$cache[$class];
        }

        $reflection = new ReflectionClass($class);
        $fields = [];
        foreach ($class::fields(new FieldFactory()) as $field) {
            if ($field->name === 'id') {
                throw new InvalidConfigException(sprintf('%s: "id" is reserved for the record id; name the field something else.', $class));
            }
            $fields[$field->name] = $field;
        }

        $storage = null;
        foreach ([PostType::class, Table::class, OptionStorage::class] as $attribute) {
            foreach ($reflection->getAttributes($attribute) as $found) {
                if ($storage !== null) {
                    throw new InvalidConfigException(sprintf('%s declares more than one storage attribute.', $class));
                }
                $storage = $found->newInstance();
            }
        }

        $taxonomies = array_map(static fn ($a) => $a->newInstance(), $reflection->getAttributes(Taxonomy::class));
        if ($taxonomies !== [] && !$storage instanceof PostType) {
            throw new InvalidConfigException(sprintf('%s: #[Taxonomy] needs #[PostType] storage.', $class));
        }

        $definition = new self($class, $fields, $storage, $taxonomies);
        self::$cache[$class] = $definition;

        return $definition;
    }

    public function field(string $name): Field
    {
        return $this->fields[$name] ?? throw new InvalidConfigException(sprintf('%s has no field "%s".', $this->class, $name));
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /**
     * @return list<string>
     */
    public function taxonomyNames(): array
    {
        return array_map(static fn (Taxonomy $t): string => $t->name, $this->taxonomies);
    }

    /**
     * @return T
     */
    public function newEntity(): Entity
    {
        return new ($this->class)();
    }
}
