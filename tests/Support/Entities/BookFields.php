<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Field\FieldFactory;

/**
 * The fields every contract-test book has, whatever its storage.
 */
trait BookFields
{
    public static function fields(FieldFactory $f): array
    {
        return [
            $f->text('title')->required(),
            $f->number('pages'),
            $f->select('format', ['paperback' => 'Paperback', 'hardcover' => 'Hardcover'])->default('paperback'),
            $f->checkbox('featured'),
            $f->media('images')->multiple(),
            $f->text('api_key')->sensitive(),
        ];
    }
}
