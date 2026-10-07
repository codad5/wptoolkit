<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Migrations;

/**
 * Move a post meta key to a new name on every post of a type, in batches, keeping every row (so
 * multiple fields keep all their values). Reversible.
 *
 *     new RenameMetaKey('2026_10_07_120000_rename_rating', 'silverbird_movies', '_silverbird_movies_rating', '_movie_rating')
 *
 * Each batch takes posts that still have the old key, so an interrupted batch repeats safely: a post
 * is rewritten from its old rows (new rows cleared first), then its old rows are deleted.
 */
final class RenameMetaKey extends BatchedMigration
{
    public function __construct(
        private readonly string $id,
        private readonly string $postType,
        private readonly string $from,
        private readonly string $to,
        private readonly int $size = 100
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function batchSize(): int
    {
        return $this->size;
    }

    public function batch(int $size): bool
    {
        return $this->move($this->from, $this->to, $size);
    }

    public function down(): void
    {
        while ($this->move($this->to, $this->from, $this->size)) {
            // keep going
        }
    }

    private function move(string $from, string $to, int $size): bool
    {
        $ids = get_posts([
            'post_type' => $this->postType,
            'post_status' => array_keys(get_post_stati()),
            'meta_key' => $from, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- a one-off migration.
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'posts_per_page' => $size,
            'no_found_rows' => true,
            'suppress_filters' => true,
        ]);

        foreach ($ids as $id) {
            $id = (int) $id;
            $rows = get_post_meta($id, $from, false);
            delete_post_meta($id, $to);
            foreach (is_array($rows) ? $rows : [] as $value) {
                add_post_meta($id, $to, wp_slash($value));
            }
            delete_post_meta($id, $from);
        }

        return count($ids) === $size;
    }
}
