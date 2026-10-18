<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Widgets;

/**
 * How fast to assemble.
 */
enum Mode: string
{
    case Fast = 'fast';
    case Slow = 'slow';
}
