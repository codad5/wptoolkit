<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Query;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * An immutable, storage-neutral query. Every adapter compiles it its own way (WP_Query args,
 * prepared SQL, an in-memory filter) with the same results; the shared contract tests prove it.
 *
 *     $books->query(Query::create()->where('pages', '>=', 100)->whereTerm('genre', ['sci-fi'])->orderBy('title')->page(2));
 *
 * Page size is capped at MAX_PER_PAGE: there is no "all rows" query (S1). Iterate pages instead.
 */
final class Query
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /** @var list<Condition> */
    private array $conditions = [];

    /** @var array<string, list<int|string>> */
    private array $terms = [];

    /** @var list<array{string, 'ASC'|'DESC'}> */
    private array $order = [];

    private int $perPage = self::DEFAULT_PER_PAGE;

    private int $page = 1;

    /** @var list<string>|null */
    private ?array $statuses = null;

    public static function create(): self
    {
        return new self();
    }

    /**
     * `where('pages', '>=', 100)`, or `where('format', 'paperback')` for equality.
     */
    public function where(string $field, mixed $operatorOrValue, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            [$operator, $value] = [Operator::Equals, $operatorOrValue];
        } else {
            $operator = match (true) {
                $operatorOrValue instanceof Operator => $operatorOrValue,
                is_string($operatorOrValue) => Operator::parse($operatorOrValue),
                default => throw new InvalidConfigException(sprintf('where("%s", …): the operator must be a string or an Operator.', $field)),
            };
        }

        if ($operator->takesList() && !is_array($value)) {
            throw new InvalidConfigException(sprintf('where("%s", "%s", …) needs a list of values.', $field, $operator->value));
        }
        if (!$operator->takesList() && !is_scalar($value) && $value !== null) {
            throw new InvalidConfigException(sprintf('where("%s", "%s", …) needs a single scalar value.', $field, $operator->value));
        }

        $copy = clone $this;
        $copy->conditions[] = new Condition($field, $operator, $operator->takesList() ? array_values((array) $value) : $value);

        return $copy;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $field, array $values): self
    {
        return $this->where($field, Operator::In, $values);
    }

    /**
     * Records with at least one of these terms (IDs or slugs). Post-type entities only.
     *
     * @param list<int|string> $terms
     */
    public function whereTerm(string $taxonomy, array $terms): self
    {
        $copy = clone $this;
        $copy->terms[$taxonomy] = $terms;

        return $copy;
    }

    /**
     * Post statuses to include (post-type entities). Default: every status except trash and auto-draft.
     */
    public function status(string ...$statuses): self
    {
        $copy = clone $this;
        $copy->statuses = array_values($statuses);

        return $copy;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new InvalidConfigException(sprintf('Order direction must be ASC or DESC, got "%s".', $direction));
        }

        $copy = clone $this;
        $copy->order[] = [$field, $direction];

        return $copy;
    }

    /**
     * Rows per page, clamped to 1..MAX_PER_PAGE.
     */
    public function perPage(int $perPage): self
    {
        $copy = clone $this;
        $copy->perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        return $copy;
    }

    public function page(int $page): self
    {
        $copy = clone $this;
        $copy->page = max(1, $page);

        return $copy;
    }

    /**
     * @return list<Condition>
     */
    public function conditions(): array
    {
        return $this->conditions;
    }

    /**
     * @return array<string, list<int|string>>
     */
    public function terms(): array
    {
        return $this->terms;
    }

    /**
     * @return list<array{string, 'ASC'|'DESC'}> Default: by id, ascending.
     */
    public function order(): array
    {
        return $this->order === [] ? [['id', 'ASC']] : $this->order;
    }

    /**
     * @return list<string>|null
     */
    public function statuses(): ?array
    {
        return $this->statuses;
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->page;
    }
}
