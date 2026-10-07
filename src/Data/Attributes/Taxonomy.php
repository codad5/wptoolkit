<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Attributes;

use Attribute;

/**
 * A taxonomy attached to a post-type entity. The repository loads and saves its terms
 * (`$book->terms('genre')`, `$book->setTerms('genre', [...])`).
 *
 *     #[Taxonomy('genre', hierarchical: true)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Taxonomy
{
    /**
     * @param array<string, mixed> $args Extra register_taxonomy() arguments.
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $hierarchical = false,
        public readonly bool $public = false,
        public readonly ?string $singular = null,
        public readonly ?string $plural = null,
        public readonly array $args = [],
        public readonly bool $register = true
    ) {
    }
}
