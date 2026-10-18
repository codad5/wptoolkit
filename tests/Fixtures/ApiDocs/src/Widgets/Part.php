<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Widgets;

/**
 * Something a gadget is made of.
 */
interface Part
{
    /**
     * The part's name.
     */
    public function label(): string;
}
