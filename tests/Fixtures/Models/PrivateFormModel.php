<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Models;

use Codad5\WPToolkit\DB\Model;

/**
 * Like the contact-form submission post types in real consumer themes:
 * registered with 'public' => false.
 */
final class PrivateFormModel extends Model
{
    use RecordsSearches;

    protected const POST_TYPE = 'contact_form';

    protected static function get_post_type_args(): array
    {
        return ['public' => false];
    }
}
