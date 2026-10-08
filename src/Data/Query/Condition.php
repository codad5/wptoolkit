<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Query;

/**
 * One `where` clause: a field, an operator and a value (a list for IN / NOT IN).
 */
final class Condition
{
    public function __construct(
        public readonly string $field,
        public readonly Operator $operator,
        public readonly mixed $value
    ) {
    }
}
