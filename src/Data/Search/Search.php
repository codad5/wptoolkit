<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Search;

use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\EntityDefinition;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use WP_Post;
use WP_Query;

/**
 * Ranked search over a #[PostType] entity — rebuilt to fix 0.x's C1 and S1:
 *
 * - **OR across sources.** Title/content, allow-listed meta fields and taxonomy terms are separate
 *   sub-queries whose IDs are unioned; 0.x ANDed `s` with the meta query, so "foo" had to be in the
 *   title *and* the meta.
 * - **Visibility by capability**, never by `is_admin()` (true on admin-ajax): people who can edit the
 *   post type see what they may read; everyone else sees published posts of viewable types only.
 * - **Allow-lists**: only fields named in `in()` are searched and only those in `expose()` are returned;
 *   sensitive fields can be neither. Pages are capped like every Query.
 *
 *     $result = (new Search(Book::class, $books, $identity))
 *         ->in('title', 'content', 'isbn', 'genre')
 *         ->expose('isbn')
 *         ->run($term, page: 1);
 *
 * @template T of Entity
 */
final class Search
{
    /** Upper bound on matches gathered per source before ranking. */
    public const MAX_CANDIDATES = 500;

    public const MIN_TERM_LENGTH = 2;

    private const COLUMN_FIELDS = ['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt'];

    /** @var list<string> */
    private array $columns = [];

    /** @var list<string> */
    private array $metaFields = [];

    /** @var list<string> */
    private array $taxonomies = [];

    /** @var list<string> */
    private array $exposed = [];

    /** @var list<Scorer> */
    private array $scorers = [];

    private readonly PostType $postType;

    /** @var EntityDefinition<T> */
    private readonly EntityDefinition $definition;

    /**
     * @param class-string<T> $entityClass
     * @param Repository<T> $repository
     */
    public function __construct(string $entityClass, private readonly Repository $repository, private readonly Identity $identity)
    {
        $this->definition = EntityDefinition::of($entityClass);
        $storage = $this->definition->storage;
        if (!$storage instanceof PostType) {
            throw new InvalidConfigException(sprintf('Search needs a #[PostType] entity; %s is not one.', $entityClass));
        }
        $this->postType = $storage;
    }

    /**
     * What to search: `title`, `content`, `excerpt`, entity field names (stored as meta) and
     * declared taxonomies (matched by term name).
     *
     * @return self<T>
     */
    public function in(string ...$sources): self
    {
        $copy = clone $this;
        foreach ($sources as $source) {
            if (isset(self::COLUMN_FIELDS[$source])) {
                $copy->columns[] = $source;
            } elseif (in_array($source, $this->definition->taxonomyNames(), true)) {
                $copy->taxonomies[] = $source;
            } elseif ($this->definition->hasField($source) && !PostType::isColumn($source)) {
                $this->assertNotSensitive($source);
                $copy->metaFields[] = $source;
            } else {
                throw new InvalidConfigException(sprintf(
                    'Cannot search "%s": it is not a post column, field or taxonomy of %s.',
                    $source,
                    $this->definition->class
                ));
            }
        }

        return $copy;
    }

    /**
     * Fields included in SearchResult::toArray(), beyond id, title and score.
     *
     * @return self<T>
     */
    public function expose(string ...$fields): self
    {
        foreach ($fields as $field) {
            $this->definition->field($field);
            $this->assertNotSensitive($field);
        }
        $copy = clone $this;
        $copy->exposed = array_values(array_unique([...$this->exposed, ...$fields]));

        return $copy;
    }

    /**
     * Replace the default scorers (title, content, searched fields).
     *
     * @return self<T>
     */
    public function scoreWith(Scorer ...$scorers): self
    {
        $copy = clone $this;
        $copy->scorers = array_values($scorers);

        return $copy;
    }

    public function run(string $term, int $page = 1, int $perPage = Query::DEFAULT_PER_PAGE): SearchResult
    {
        $perPage = max(1, min(Query::MAX_PER_PAGE, $perPage));
        $page = max(1, $page);
        $term = trim(wp_strip_all_tags($term));
        $visibility = $this->visibility();

        if (mb_strlen($term) < self::MIN_TERM_LENGTH || $visibility === null) {
            return new SearchResult([], 0, $page, $perPage, $this->exposed);
        }

        $ids = array_values(array_unique([
            ...$this->matchColumns($term, $visibility),
            ...$this->matchMeta($term, $visibility),
            ...$this->matchTerms($term, $visibility),
        ]));

        $scorers = $this->scorers !== [] ? $this->scorers : [Scorers::title(), Scorers::content(), Scorers::fields($this->metaFields)];
        $ranked = [];
        foreach ($this->repository->findMany($ids) as $entity) {
            $post = get_post((int) $entity->id());
            if (!$post instanceof WP_Post) {
                continue;
            }
            $score = 0.0;
            foreach ($scorers as $scorer) {
                $score += $scorer->score($term, $post, $entity);
            }
            $ranked[] = ['entity' => $entity, 'title' => $post->post_title, 'score' => $score, 'id' => $post->ID];
        }

        usort($ranked, static fn (array $a, array $b): int => [$b['score'], $b['id']] <=> [$a['score'], $a['id']]);
        $items = array_map(
            static fn (array $r): array => ['entity' => $r['entity'], 'title' => $r['title'], 'score' => $r['score']],
            array_slice($ranked, ($page - 1) * $perPage, $perPage)
        );

        return new SearchResult($items, count($ranked), $page, $perPage, $this->exposed);
    }

    /**
     * WP_Query status arguments for the current user, or null when they may not search at all.
     *
     * @return array<string, mixed>|null
     */
    private function visibility(): ?array
    {
        $type = get_post_type_object($this->postType->name);
        if ($type === null) {
            return null;
        }

        $editCap = $type->cap->edit_posts ?? 'edit_posts';
        if (is_string($editCap) && current_user_can($editCap)) {
            return ['post_status' => ['publish', 'private', 'draft', 'pending', 'future'], 'perm' => 'readable'];
        }

        return is_post_type_viewable($type) ? ['post_status' => 'publish'] : null;
    }

    /**
     * @param array<string, mixed> $visibility
     * @return list<int>
     */
    private function matchColumns(string $term, array $visibility): array
    {
        if ($this->columns === []) {
            return [];
        }

        return $this->ids($visibility + [
            's' => $term,
            'search_columns' => array_map(static fn (string $c): string => self::COLUMN_FIELDS[$c], $this->columns),
        ]);
    }

    /**
     * @param array<string, mixed> $visibility
     * @return list<int>
     */
    private function matchMeta(string $term, array $visibility): array
    {
        if ($this->metaFields === []) {
            return [];
        }

        $clauses = ['relation' => 'OR'];
        foreach ($this->metaFields as $field) {
            $clauses[] = ['key' => $this->postType->metaKey($this->identity, $field), 'value' => $term, 'compare' => 'LIKE'];
        }

        return $this->ids($visibility + ['meta_query' => $clauses]); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_query -- allow-listed keys only.
    }

    /**
     * @param array<string, mixed> $visibility
     * @return list<int>
     */
    private function matchTerms(string $term, array $visibility): array
    {
        if ($this->taxonomies === []) {
            return [];
        }

        $termIds = get_terms([
            'taxonomy' => $this->taxonomies,
            'name__like' => $term,
            'fields' => 'ids',
            'hide_empty' => false,
            'number' => self::MAX_CANDIDATES,
        ]);
        if (!is_array($termIds) || $termIds === []) {
            return [];
        }

        $clauses = ['relation' => 'OR'];
        foreach ($this->taxonomies as $taxonomy) {
            $clauses[] = ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array_map('intval', $termIds)];
        }

        return $this->ids($visibility + ['tax_query' => $clauses]); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_tax_query -- declared taxonomies only.
    }

    /**
     * @param array<string, mixed> $args
     * @return list<int>
     */
    private function ids(array $args): array
    {
        $query = new WP_Query($args + [
            'post_type' => $this->postType->name,
            'fields' => 'ids',
            'posts_per_page' => self::MAX_CANDIDATES,
            'orderby' => 'ID',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $ids = [];
        foreach (is_array($query->posts) ? $query->posts : [] as $post) {
            $ids[] = $post instanceof WP_Post ? $post->ID : (int) $post;
        }

        return $ids;
    }

    private function assertNotSensitive(string $field): void
    {
        if ($this->definition->field($field)->isSensitive()) {
            throw new InvalidConfigException(sprintf('Field "%s" is sensitive and can be neither searched nor exposed (ADR-0021).', $field));
        }
    }
}
