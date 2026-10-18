<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Widgets\Deep;

use Codad5\WPToolkit\Widgets\Gadget;

/**
 * A class in a sub-namespace, which takes a Gadget.
 */
final class Nested
{
    /**
     * @param Gadget $gadget The one to use.
     */
    public function use(Gadget $gadget): ?Gadget
    {
        return $gadget;
    }
}
