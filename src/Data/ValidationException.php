<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data;

use Codad5\WPToolkit\Exceptions\WPToolkitException;
use RuntimeException;

/**
 * An entity failed validation on save; nothing was written. `errors()` lists messages per field.
 */
final class ValidationException extends RuntimeException implements WPToolkitException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private readonly array $errors)
    {
        $first = array_merge(...array_values($errors))[0] ?? 'Invalid data.';
        parent::__construct(count($errors) === 1 ? $first : sprintf('%s (and %d more invalid fields)', $first, count($errors) - 1));
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
