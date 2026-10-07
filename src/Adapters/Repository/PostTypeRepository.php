<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Query\Condition;
use Codad5\WPToolkit\Data\Query\Operator;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\RepositoryException;
use Codad5\WPToolkit\Foundation\Identity;
use WP_Post;
use WP_Query;

/**
 * Entities as posts of a custom post type (#[PostType]). Fields named after post columns live on
 * the post; the rest are post meta with the keys a MetaBox uses (ADR-0016), one row per value for
 * multiple fields; #[Taxonomy] terms are loaded and saved as slugs.
 *
 * Queries compile to WP_Query (meta_query, tax_query) plus a prepared `posts_where` clause for
 * post columns. WordPress's own post and meta caches apply, so there is no extra cache layer to go stale.
 *
 * @template T of Entity
 * @extends BaseRepository<T>
 */
final class PostTypeRepository extends BaseRepository
{
    private const POST_COLUMNS = PostType::COLUMNS;

    /** Entity field name => WP_Query orderby key. */
    private const ORDER_KEYS = [
        'id' => 'ID',
        'title' => 'title',
        'slug' => 'name',
        'date' => 'date',
        'author' => 'author',
        'parent' => 'parent',
        'menu_order' => 'menu_order',
        'content' => 'post_content',
        'excerpt' => 'post_excerpt',
        'status' => 'post_status',
    ];

    private readonly PostType $postType;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(string $entityClass, FieldTypes $types, private readonly Identity $identity)
    {
        parent::__construct($entityClass, $types);

        $storage = $this->definition->storage;
        if (!$storage instanceof PostType) {
            throw new InvalidConfigException(sprintf('%s needs #[PostType] to use PostTypeRepository.', $entityClass));
        }
        $this->postType = $storage;
    }

    public function metaKey(string $field): string
    {
        return $this->identity->metaKey($this->postType->box, $this->postType->name, $field, $this->postType->metaPrefix);
    }

    public function find(int $id): ?Entity
    {
        $post = $id > 0 ? get_post($id) : null;
        if (!$post instanceof WP_Post || $post->post_type !== $this->postType->name) {
            return null;
        }

        return $this->hydrate($post);
    }

    public function query(?Query $query = null): array
    {
        $query ??= Query::create();
        $wpQuery = $this->run($query, ['posts_per_page' => $query->limit(), 'offset' => $query->offset(), 'no_found_rows' => true]);

        $entities = [];
        foreach (is_array($wpQuery->posts) ? $wpQuery->posts : [] as $post) {
            if ($post instanceof WP_Post) {
                $entities[] = $this->hydrate($post);
            }
        }

        return $entities;
    }

    public function count(?Query $query = null): int
    {
        $wpQuery = $this->run($query ?? Query::create(), ['posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false]);

        return (int) $wpQuery->found_posts;
    }

    public function save(Entity $entity): Entity
    {
        $this->assertValid($entity);

        $fields = $this->definition->fields;
        $dirty = $entity->exists() ? $entity->dirty() : array_fill_keys(array_keys($fields), true);

        $post = ['post_type' => $this->postType->name];
        $meta = [];
        foreach ($dirty as $name => $_) {
            $stored = $this->toStorage($fields[$name], $entity->get($name));
            if (isset(self::POST_COLUMNS[$name])) {
                $post[self::POST_COLUMNS[$name]] = $stored ?? '';
            } else {
                $meta[$name] = $stored;
            }
        }

        $id = $entity->id();
        if ($id === null) {
            $post['post_status'] ??= 'publish';
            $result = wp_insert_post(wp_slash($post), true);
        } else {
            $post['ID'] = $id;
            $result = count($post) > 2 ? wp_update_post(wp_slash($post), true) : $id;
        }
        if (is_wp_error($result) || $result === 0) {
            throw RepositoryException::writeFailed($this->definition->class, is_wp_error($result) ? $result->get_error_message() : 'no post id returned');
        }

        foreach ($meta as $name => $stored) {
            $this->writeMeta($result, $fields[$name], $stored);
        }
        foreach ($entity->changedTaxonomies() as $taxonomy) {
            $terms = wp_set_object_terms($result, $entity->terms($taxonomy), $taxonomy);
            if (is_wp_error($terms)) {
                throw RepositoryException::writeFailed($this->definition->class, $terms->get_error_message());
            }
        }

        clean_post_cache($result);
        $fresh = get_post($result);
        if ($fresh instanceof WP_Post) {
            $loaded = $this->hydrate($fresh);
            $entity->markPersisted($result, $this->values($loaded), $this->termsOf($result));
        }

        return $entity;
    }

    public function delete(Entity|int $entity): bool
    {
        $id = $entity instanceof Entity ? $entity->id() : $entity;
        if ($id === null || $this->find($id) === null) {
            return false;
        }

        $deleted = wp_delete_post($id, true);
        if ($deleted instanceof WP_Post && $entity instanceof Entity) {
            $entity->markDeleted();
        }

        return $deleted instanceof WP_Post;
    }

    private function writeMeta(int $postId, Field $field, mixed $stored): void
    {
        $key = $this->metaKey($field->name);

        if ($field->isMultiple()) {
            delete_post_meta($postId, $key);
            foreach (is_array($stored) ? $stored : [] as $value) {
                add_post_meta($postId, $key, wp_slash($value));
            }
            return;
        }

        if ($stored === null) {
            delete_post_meta($postId, $key);
            return;
        }

        update_post_meta($postId, $key, wp_slash($stored));
    }

    /**
     * @return T
     */
    private function hydrate(WP_Post $post): Entity
    {
        $values = [];
        foreach ($this->definition->fields as $name => $field) {
            if (isset(self::POST_COLUMNS[$name])) {
                $column = self::POST_COLUMNS[$name];
                $raw = $post->{$column};
                $values[$name] = $this->fromStorage($field, $raw === '' ? null : $raw);
                continue;
            }

            $rows = get_post_meta($post->ID, $this->metaKey($name), false);
            $rows = is_array($rows) ? array_values($rows) : [];
            $stored = $field->isMultiple() ? ($rows === [] ? null : $rows) : ($rows[0] ?? null);
            $values[$name] = $this->fromStorage($field, $stored);
        }

        $entity = $this->definition->newEntity();
        $entity->markPersisted($post->ID, $values, $this->termsOf($post->ID));

        return $entity;
    }

    /**
     * @return array<string, list<int|string>>
     */
    private function termsOf(int $postId): array
    {
        $terms = [];
        foreach ($this->definition->taxonomyNames() as $taxonomy) {
            $slugs = wp_get_object_terms($postId, $taxonomy, ['fields' => 'slugs']);
            $terms[$taxonomy] = is_array($slugs) ? array_values(array_map('strval', $slugs)) : [];
        }

        return $terms;
    }

    /**
     * @param T $entity
     * @return array<string, mixed>
     */
    private function values(Entity $entity): array
    {
        $values = [];
        foreach (array_keys($this->definition->fields) as $name) {
            $values[$name] = $entity->get($name);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function run(Query $query, array $extra): WP_Query
    {
        $this->assertQueryable($query);

        $args = $extra + [
            'post_type' => $this->postType->name,
            'post_status' => $query->statuses() ?? 'any',
            'ignore_sticky_posts' => true,
            'suppress_filters' => false,
            'update_post_term_cache' => $this->definition->taxonomies !== [],
        ];

        $metaQuery = [];
        $where = [];
        foreach ($query->conditions() as $condition) {
            if ($condition->field === 'id' || isset(self::POST_COLUMNS[$condition->field])) {
                $where[] = $condition;
            } else {
                $metaQuery[] = $this->metaClause($condition);
            }
        }

        $orderby = [];
        foreach ($query->order() as $i => [$field, $direction]) {
            if (isset(self::ORDER_KEYS[$field])) {
                $orderby[self::ORDER_KEYS[$field]] = $direction;
                continue;
            }
            $clause = 'wptoolkit_order_' . $i;
            $metaQuery[] = [
                'relation' => 'OR',
                $clause => ['key' => $this->metaKey($field), 'compare' => 'EXISTS'] + $this->metaType($field),
                ['key' => $this->metaKey($field), 'compare' => 'NOT EXISTS'],
            ];
            $orderby[$clause] = $direction;
        }
        $orderby['ID'] ??= 'ASC';
        $args['orderby'] = $orderby;

        if ($metaQuery !== []) {
            $args['meta_query'] = ['relation' => 'AND', ...$metaQuery];
        }

        $taxQuery = [];
        foreach ($query->terms() as $taxonomy => $terms) {
            $numeric = $terms !== [] && array_filter($terms, 'is_int') === $terms;
            $taxQuery[] = ['taxonomy' => $taxonomy, 'field' => $numeric ? 'term_id' : 'slug', 'terms' => $terms, 'operator' => 'IN'];
        }
        if ($taxQuery !== []) {
            $args['tax_query'] = ['relation' => 'AND', ...$taxQuery];
        }

        if ($where === []) {
            return new WP_Query($args);
        }

        $token = uniqid('wptoolkit_', true);
        $args['wptoolkit_where'] = $token;
        $filter = function (string $sql, WP_Query $q) use ($token, $where): string {
            return $q->get('wptoolkit_where') === $token ? $sql . $this->columnSql($where) : $sql;
        };

        add_filter('posts_where', $filter, 10, 2);
        try {
            return new WP_Query($args);
        } finally {
            remove_filter('posts_where', $filter, 10);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    private function metaClause(Condition $condition): array
    {
        $key = $this->metaKey($condition->field);
        $value = $this->conditionToStorage($condition);
        $type = $this->metaType($condition->field);

        if ($value === null && !$condition->operator->takesList()) {
            return match ($condition->operator) {
                Operator::Equals => ['key' => $key, 'compare' => 'NOT EXISTS'],
                Operator::NotEquals => ['key' => $key, 'compare' => 'EXISTS'],
                default => throw new InvalidConfigException(sprintf('"%s" cannot compare against null.', $condition->operator->value)),
            };
        }

        $clause = ['key' => $key, 'value' => $value, 'compare' => strtoupper($condition->operator->value)] + $type;

        // A record without the meta row is "not equal" too, as in the other adapters.
        if ($condition->operator === Operator::NotEquals || $condition->operator === Operator::NotIn) {
            return ['relation' => 'OR', $clause, ['key' => $key, 'compare' => 'NOT EXISTS']];
        }

        return $clause;
    }

    /**
     * @return array{type?: string}
     */
    private function metaType(string $field): array
    {
        return $this->definition->field($field)->type === 'number' ? ['type' => 'DECIMAL(20,6)'] : [];
    }

    /**
     * Prepared SQL for conditions on post columns.
     *
     * @param list<Condition> $conditions
     */
    private function columnSql(array $conditions): string
    {
        global $wpdb;

        $sql = '';
        foreach ($conditions as $condition) {
            $column = $wpdb->posts . '.' . ($condition->field === 'id' ? 'ID' : self::POST_COLUMNS[$condition->field]);
            $value = $this->conditionToStorage($condition);

            if ($condition->operator->takesList()) {
                $list = array_values(array_filter((array) $value, static fn ($v) => $v !== null));
                if ($list === []) {
                    $sql .= $condition->operator === Operator::In ? ' AND 1=0' : '';
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($list), '%s'));
                $not = $condition->operator === Operator::NotIn ? 'NOT ' : '';
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- column from a fixed map; placeholders built to match.
                $sql .= ' AND ' . $wpdb->prepare("{$column} {$not}IN ({$placeholders})", ...$list);
                continue;
            }

            if ($condition->operator === Operator::Like) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column from a fixed map.
                $sql .= ' AND ' . $wpdb->prepare("{$column} LIKE %s", '%' . $wpdb->esc_like((string) (is_scalar($value) ? $value : '')) . '%');
                continue;
            }

            $operator = $condition->operator->value;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column from a fixed map, operator from the Operator enum.
            $sql .= ' AND ' . $wpdb->prepare("{$column} {$operator} %s", is_scalar($value) ? (string) $value : '');
        }

        return $sql;
    }
}
