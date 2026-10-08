<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support;

use Brain\Monkey\Functions;

/**
 * In-memory post meta behaving like WordPress's: each key holds a list of rows; `$single` returns
 * the first row or ''; add_post_meta() appends; update_post_meta() replaces all rows with one; both unslash.
 */
final class FakePostMeta
{
    /** @var array<int, array<string, list<mixed>>> */
    public array $meta = [];

    public function install(): void
    {
        Functions\when('get_post_meta')->alias(function (int $postId, string $key = '', bool $single = false) {
            if ($key === '') {
                return $this->meta[$postId] ?? [];
            }
            $rows = $this->meta[$postId][$key] ?? [];

            return $single ? ($rows[0] ?? '') : $rows;
        });
        Functions\when('metadata_exists')->alias(fn (string $type, int $postId, string $key) => isset($this->meta[$postId][$key]));
        Functions\when('add_post_meta')->alias(function (int $postId, string $key, $value) {
            $this->meta[$postId][$key][] = self::unslash($value);
            return true;
        });
        Functions\when('update_post_meta')->alias(function (int $postId, string $key, $value) {
            $this->meta[$postId][$key] = [self::unslash($value)];
            return true;
        });
        Functions\when('delete_post_meta')->alias(function (int $postId, string $key) {
            unset($this->meta[$postId][$key]);
            return true;
        });
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_slash')->alias(static fn ($v) => is_string($v) ? addslashes($v) : $v);
    }

    /**
     * Like WordPress, the add/update functions unslash what they are given.
     */
    private static function unslash(mixed $value): mixed
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}
