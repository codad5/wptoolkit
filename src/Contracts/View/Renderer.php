<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\View;

use Codad5\WPToolkit\Exceptions\ViewException;

/**
 * Turns a named view and its data into HTML. The built-in adapter runs PHP templates
 * (`View\PhpTemplateRenderer`); a Twig or Blade adapter can implement this in its own package.
 */
interface Renderer
{
    /**
     * @param array<string, mixed> $data
     * @throws ViewException When the view is missing or fails while rendering.
     */
    public function render(string $view, array $data = []): string;

    public function exists(string $view): bool;
}
