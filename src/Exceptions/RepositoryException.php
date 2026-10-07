<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

use RuntimeException;

/**
 * Storage refused a write (a WP_Error from wp_insert_post(), a failed `$wpdb` query).
 */
final class RepositoryException extends RuntimeException implements WPToolkitException
{
    public static function writeFailed(string $entityClass, string $reason): self
    {
        return new self(sprintf('Could not save %s: %s', $entityClass, $reason));
    }
}
