<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Log;

/**
 * A logger that can be told which context keys hold secrets, beyond its own name heuristics.
 * Settings registers its sensitive fields here (ADR-0021). Optional: callers check `instanceof`.
 */
interface RedactsKeys
{
    public function redactKeys(string ...$keys): void;
}
