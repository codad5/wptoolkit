<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldFactory;

/**
 * A post type that is not public: only people who can edit it may search it (S1).
 */
#[PostType('wptk_secret', columns: ['title' => 'post_title'])]
final class SecretBook extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [$f->text('title')];
    }
}
