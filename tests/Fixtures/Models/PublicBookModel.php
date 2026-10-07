<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Models;

use Codad5\WPToolkit\DB\Model;

final class PublicBookModel extends Model
{
    use RecordsSearches;

    protected const POST_TYPE = 'book';

    protected static function get_post_type_args(): array
    {
        return [];
    }
}
