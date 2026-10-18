<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Widgets;

use Closure;
use InvalidArgumentException;

/**
 * A gadget. Assembles parts (ADR-0007).
 *
 * The second paragraph says why, with `code` in it.
 *
 * @since 1.2.0
 */
final class Gadget implements Part
{
    /** How many parts fit. */
    public const CAPACITY = 8;

    public const RELEASE = '1.0.0-rc.9';

    /**
     * @param array<string, int> $sizes Part sizes by name.
     * @param (Closure(string): void)|null $notify Called with each name.
     */
    public function __construct(public readonly array $sizes = [], private readonly ?Closure $notify = null)
    {
    }

    /**
     * Assemble the gadget.
     *
     * @param string $name What to call it.
     * @param int|string $count How many, as a number
     *        or a word.
     * @return list<string> The part names.
     * @throws InvalidArgumentException When the name is empty.
     */
    public static function assemble(string $name, int|string $count = 3, bool $strict = false): array
    {
        return [];
    }

    /**
     * @internal Hooked to init.
     */
    public function onInit(): void
    {
    }

    public function label(): string
    {
        return 'gadget';
    }

    public function __toString(): string
    {
        return '';
    }

    private function secret(): void
    {
    }
}
