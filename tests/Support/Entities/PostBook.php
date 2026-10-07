<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Attributes\Taxonomy;
use Codad5\WPToolkit\Data\Entity;

#[PostType('wptk_book', columns: ['title' => 'post_title'])]
#[Taxonomy('wptk_genre')]
final class PostBook extends Entity
{
    use BookFields;
}
