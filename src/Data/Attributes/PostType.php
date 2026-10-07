<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Attributes;

use Attribute;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Store an entity as a custom post type. Every field is post meta under `{box}_{post_type}_{field}`
 * — the keys a `MetaBox` with id `$box` uses — or under `$metaPrefix` when set (0.x's
 * `set_prefix()`), unless `$columns` maps it to a wp_posts column. The mapping is explicit so a
 * 0.x meta field that happens to be called `title` or `status` stays in meta (ADR-0016).
 *
 *     #[PostType('book', public: true, columns: ['title' => 'post_title', 'summary' => 'post_excerpt'])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PostType
{
    /** wp_posts columns a field may map to => WP_Query orderby key (null: not orderable). */
    public const COLUMNS = [
        'post_title' => 'title',
        'post_content' => null,
        'post_excerpt' => null,
        'post_status' => null,
        'post_name' => 'name',
        'post_date' => 'date',
        'post_author' => 'author',
        'post_parent' => 'parent',
        'menu_order' => 'menu_order',
    ];

    /**
     * @param array<string, mixed> $args Extra register_post_type() arguments.
     * @param array<string, string> $columns Entity field => wp_posts column (a key of COLUMNS).
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $public = false,
        public readonly ?string $singular = null,
        public readonly ?string $plural = null,
        public readonly array $args = [],
        public readonly string $box = 'details',
        public readonly ?string $metaPrefix = null,
        public readonly bool $register = true,
        public readonly array $columns = []
    ) {
        foreach ($columns as $field => $column) {
            if (!array_key_exists($column, self::COLUMNS)) {
                throw new InvalidConfigException(sprintf(
                    'Field "%s" maps to "%s", which is not a supported post column (%s).',
                    $field,
                    $column,
                    implode(', ', array_keys(self::COLUMNS))
                ));
            }
        }
    }

    /**
     * Where a non-column field is stored: the key a MetaBox with id `$box` on this post type uses.
     */
    public function metaKey(Identity $identity, string $field): string
    {
        return $identity->metaKey($this->box, $this->name, $field, $this->metaPrefix);
    }

    /**
     * The wp_posts column a field lives in, or null when it is meta.
     */
    public function columnFor(string $field): ?string
    {
        return $this->columns[$field] ?? null;
    }
}
