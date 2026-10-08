<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldFactory;

/**
 * Shaped like a 0.x theme's movie model: a custom meta prefix (0.x `set_prefix()`), multiple media
 * stored one row per ID, and a list stored as one serialized row.
 */
#[PostType('acme_movies', box: 'movie_details', metaPrefix: '_acme_movies_')]
final class MovieFixture extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [
            $f->select('availability', ['now_showing' => 'Now showing', 'coming_soon' => 'Coming soon']),
            $f->of('gallery', 'wp_media')->multiple(),
            $f->text('showtimes')->multiple(),
        ];
    }
}
