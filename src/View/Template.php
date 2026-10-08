<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\View;

use Codad5\WPToolkit\Exceptions\ViewException;

/**
 * Every template receives this as `$view`: layouts, sections and partials (0.x `ViewHelper`).
 *
 *     <?php $view->layout('layouts/admin', ['title' => $title]); ?>
 *     <?php $view->start('sidebar'); ?> … <?php $view->stop(); ?>
 *     <p>Main content becomes the layout's "content" section.</p>
 *     <?php $view->insert('partials/row', ['item' => $item]); ?>
 *
 * In the layout: `<?php $view->section('content'); ?>` and `<?php $view->section('sidebar', 'Nothing here'); ?>`.
 * Sections hold rendered (already escaped) HTML.
 */
final class Template
{
    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $layoutData = [];

    /** @var list<string> */
    private array $open = [];

    /**
     * @param array<string, string> $sections Sections from the child template, when this is a layout.
     */
    public function __construct(private readonly PhpTemplateRenderer $renderer, private array $sections = [])
    {
    }

    /**
     * @param array<string, mixed> $data Extra data for the layout.
     */
    public function layout(string $view, array $data = []): void
    {
        $this->layout = $view;
        $this->layoutData = $data;
    }

    public function start(string $section): void
    {
        $this->open[] = $section;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->open);
        if ($name === null) {
            throw ViewException::misuse('$view->stop() without a matching $view->start().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    /**
     * Print a section's HTML, or `$default` (escaped) when the child template didn't fill it.
     */
    public function section(string $name, string $default = ''): void
    {
        if (isset($this->sections[$name])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a section is rendered template output, escaped when it was rendered.
            echo $this->sections[$name];
            return;
        }

        echo esc_html($default);
    }

    public function has(string $section): bool
    {
        return isset($this->sections[$section]);
    }

    /**
     * Render another view here.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $view, array $data = []): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered template output.
        echo $this->renderer->render($view, $data);
    }

    /**
     * @internal
     * @return array{string|null, array<string, mixed>, array<string, string>, list<string>}
     */
    public function state(): array
    {
        return [$this->layout, $this->layoutData, $this->sections, $this->open];
    }
}
