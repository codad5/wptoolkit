<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data;

use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Typed data with dirty tracking and no persistence logic (ADR-0009). A repository loads and saves it.
 *
 *     #[PostType('book', public: true)]
 *     final class Book extends Entity
 *     {
 *         public static function fields(FieldFactory $f): array
 *         {
 *             return [$f->text('title')->required(), $f->text('isbn')->label(__('ISBN', 'my-plugin'))];
 *         }
 *     }
 *
 *     $book = new Book(['title' => 'Dune']);
 *     $book->isbn = '978-0441013593';
 *     $books->save($book);   // $book->id() is now set
 *
 * Values are what code reads (a checkbox is a bool, a multiple field a list); the repository
 * converts to and from storage through each field's type.
 */
abstract class Entity
{
    private ?int $id = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, mixed> */
    private array $original = [];

    /** @var array<string, list<int|string>> */
    private array $terms = [];

    /** @var array<string, true> */
    private array $termsChanged = [];

    /**
     * @return list<Field>
     */
    abstract public static function fields(FieldFactory $f): array;

    /**
     * @param array<string, mixed> $attributes
     */
    final public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function exists(): bool
    {
        return $this->id !== null;
    }

    public function get(string $name): mixed
    {
        $field = $this->definition()->field($name);

        return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $field->defaultValue();
    }

    public function set(string $name, mixed $value): static
    {
        $this->definition()->field($name);
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $name => $value) {
            $this->set($name, $value);
        }

        return $this;
    }

    /**
     * Every field's value (defaults included), without sensitive fields (ADR-0021).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $values = ['id' => $this->id];
        foreach ($this->definition()->fields as $name => $field) {
            if (!$field->isSensitive()) {
                $values[$name] = $this->get($name);
            }
        }

        return $values;
    }

    /**
     * Values set since the entity was loaded or last saved.
     *
     * @return array<string, mixed>
     */
    public function dirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $name => $value) {
            if (!array_key_exists($name, $this->original) || $this->original[$name] !== $value) {
                $dirty[$name] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(?string $name = null): bool
    {
        $dirty = $this->dirty();

        return $name === null ? ($dirty !== [] || $this->termsChanged !== []) : array_key_exists($name, $dirty);
    }

    /**
     * @return list<int|string> Term IDs or slugs, as loaded or set.
     */
    public function terms(string $taxonomy): array
    {
        $this->assertTaxonomy($taxonomy);

        return $this->terms[$taxonomy] ?? [];
    }

    /**
     * @param list<int|string> $terms Term IDs or slugs.
     */
    public function setTerms(string $taxonomy, array $terms): static
    {
        $this->assertTaxonomy($taxonomy);
        $this->terms[$taxonomy] = $terms;
        $this->termsChanged[$taxonomy] = true;

        return $this;
    }

    /**
     * @internal Repositories only.
     * @return list<string>
     */
    public function changedTaxonomies(): array
    {
        return array_keys($this->termsChanged);
    }

    /**
     * @internal Repositories only: record the stored state after a load or a save.
     *
     * @param array<string, mixed> $attributes
     * @param array<string, list<int|string>> $terms
     */
    public function markPersisted(int $id, array $attributes, array $terms = []): void
    {
        $this->id = $id;
        $this->attributes = $attributes + $this->attributes;
        $this->original = $this->attributes;
        $this->terms = $terms + $this->terms;
        $this->termsChanged = [];
    }

    /**
     * @internal Repositories only: the entity was deleted.
     */
    public function markDeleted(): void
    {
        $this->id = null;
        $this->original = [];
    }

    /**
     * @return EntityDefinition<static>
     */
    public function definition(): EntityDefinition
    {
        return EntityDefinition::of(static::class);
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function __isset(string $name): bool
    {
        return $this->definition()->hasField($name) && $this->get($name) !== null;
    }

    private function assertTaxonomy(string $taxonomy): void
    {
        if (!in_array($taxonomy, $this->definition()->taxonomyNames(), true)) {
            throw new InvalidConfigException(sprintf('%s declares no #[Taxonomy(\'%s\')].', static::class, $taxonomy));
        }
    }
}
