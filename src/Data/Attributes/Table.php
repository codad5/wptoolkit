<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Attributes;

use Attribute;

/**
 * Store an entity in a custom table `{$wpdb->prefix}{slug}_{name}`: an `id` primary key plus one
 * column per field. Create it with a migration (`TableSchema::createSql()`).
 *
 *     #[Table('books')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Table
{
    public function __construct(public readonly string $name)
    {
    }
}
