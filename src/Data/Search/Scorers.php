<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Search;

use Codad5\WPToolkit\Data\Entity;
use WP_Post;

/**
 * The built-in scorers, with 0.x's weights: a title match outranks content, content outranks meta.
 */
final class Scorers
{
    public static function title(): Scorer
    {
        return new class implements Scorer {
            public function score(string $term, WP_Post $post, Entity $entity): float
            {
                $title = mb_strtolower($post->post_title);
                $term = mb_strtolower($term);
                $score = match (true) {
                    $title === $term => 50.0,
                    str_starts_with($title, $term) => 30.0,
                    str_contains($title, $term) => 20.0,
                    default => 0.0,
                };
                foreach (Scorers::words($term) as $word) {
                    $score += str_contains($title, $word) ? 5 : 0;
                }

                return $score;
            }
        };
    }

    public static function content(): Scorer
    {
        return new class implements Scorer {
            public function score(string $term, WP_Post $post, Entity $entity): float
            {
                $content = mb_strtolower(wp_strip_all_tags($post->post_content . ' ' . $post->post_excerpt));
                $term = mb_strtolower($term);
                $score = substr_count($content, $term) * 3;
                foreach (Scorers::words($term) as $word) {
                    $score += substr_count($content, $word);
                }

                return (float) min($score, 30);
            }
        };
    }

    /**
     * @param list<string> $fields Entity fields to score — the same allow-list the search uses.
     */
    public static function fields(array $fields): Scorer
    {
        return new class ($fields) implements Scorer {
            /** @param list<string> $fields */
            public function __construct(private readonly array $fields)
            {
            }

            public function score(string $term, WP_Post $post, Entity $entity): float
            {
                $term = mb_strtolower($term);
                $score = 0.0;
                foreach ($this->fields as $field) {
                    foreach ((array) $entity->get($field) as $value) {
                        if (is_scalar($value) && str_contains(mb_strtolower((string) $value), $term)) {
                            $score += 10;
                        }
                    }
                }

                return $score;
            }
        };
    }

    /**
     * @internal
     * @return list<string>
     */
    public static function words(string $term): array
    {
        return array_values(array_filter(preg_split('/\s+/u', $term) ?: [], static fn (string $w): bool => mb_strlen($w) > 1));
    }
}
