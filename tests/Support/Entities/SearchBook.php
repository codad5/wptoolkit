<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Attributes\Taxonomy;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldFactory;

#[PostType('wptk_search', public: true, columns: ['title' => 'post_title', 'content' => 'post_content'])]
#[Taxonomy('wptk_topic')]
final class SearchBook extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [$f->text('title'), $f->text('content'), $f->text('isbn'), $f->text('notes'), $f->text('secret')->sensitive()];
    }
}
