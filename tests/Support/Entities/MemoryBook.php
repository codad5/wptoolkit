<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Attributes\Taxonomy;
use Codad5\WPToolkit\Data\Entity;

/**
 * Declares a post type and taxonomy so the in-memory adapter can exercise terms too.
 */
#[PostType('memory_book')]
#[Taxonomy('memory_genre')]
final class MemoryBook extends Entity
{
    use BookFields;
}
