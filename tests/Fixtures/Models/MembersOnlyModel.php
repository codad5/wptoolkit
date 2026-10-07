<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Models;

use Codad5\WPToolkit\DB\Model;

final class MembersOnlyModel extends Model
{
    use RecordsSearches;

    protected const POST_TYPE = 'member_doc';
    protected const REQUIRES_AUTHENTICATION = true;
    protected const VIEW_CAPABILITY = 'read_member_docs';

    protected static function get_post_type_args(): array
    {
        return [];
    }
}
