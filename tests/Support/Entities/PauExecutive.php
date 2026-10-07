<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldFactory;

/**
 * Shaped like pau's executive model: its "title" is a meta field of the executive_role box, not the
 * post title — so it must stay in meta (no column mapping).
 */
#[PostType('pau-executive', box: 'executive_role')]
final class PauExecutive extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [$f->text('title'), $f->checkbox('active')];
    }
}
