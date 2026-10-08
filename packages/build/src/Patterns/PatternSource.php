<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Patterns;

/**
 * Somewhere include/exclude patterns come from — a pattern file read at build time.
 */
interface PatternSource
{
    /**
     * @return list<string>
     */
    public function patterns(string $projectRoot): array;

    public function describe(): string;
}
