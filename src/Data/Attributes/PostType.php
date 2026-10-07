<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Attributes;

use Attribute;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Store an entity as a custom post type. Fields named after post columns (`title`, `content`,
 * `excerpt`, `status`, `slug`, `date`, `author`, `parent`, `menu_order`) live on the post; the rest
 * are post meta under `{box}_{post_type}_{field}` — the keys a `MetaBox` with id `$box` uses — or
 * under `$metaPrefix` when set (0.x's `set_prefix()`).
 *
 *     #[PostType('book', public: true, args: ['menu_icon' => 'dashicons-book'])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class PostType
{
    /** Entity field name => wp_posts column: these fields live on the post, not in meta. */
    public const COLUMNS = [
        'title' => 'post_title',
        'content' => 'post_content',
        'excerpt' => 'post_excerpt',
        'status' => 'post_status',
        'slug' => 'post_name',
        'date' => 'post_date',
        'author' => 'post_author',
        'parent' => 'post_parent',
        'menu_order' => 'menu_order',
    ];

    /**
     * @param array<string, mixed> $args Extra register_post_type() arguments.
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $public = false,
        public readonly ?string $singular = null,
        public readonly ?string $plural = null,
        public readonly array $args = [],
        public readonly string $box = 'details',
        public readonly ?string $metaPrefix = null,
        public readonly bool $register = true
    ) {
    }

    /**
     * Where a non-column field is stored: the key a MetaBox with id `$box` on this post type uses.
     */
    public function metaKey(Identity $identity, string $field): string
    {
        return $identity->metaKey($this->box, $this->name, $field, $this->metaPrefix);
    }

    public static function isColumn(string $field): bool
    {
        return array_key_exists($field, self::COLUMNS);
    }
}
