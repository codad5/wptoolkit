<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\Validation;

/**
 * One validation rule (Strategy). Write your own by implementing this; use it in route args or
 * fields alongside the built-ins.
 */
interface Rule
{
    /**
     * @param array<string, mixed> $data All input, for rules that compare fields.
     */
    public function passes(mixed $value, array $data): bool;

    /**
     * A translated message for a failure, naming the field.
     */
    public function message(string $field): string;
}
