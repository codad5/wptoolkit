<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\View;

use Codad5\WPToolkit\Contracts\View\Renderer;
use Codad5\WPToolkit\Exceptions\ViewException;
use Throwable;

/**
 * Renders PHP templates (ports 0.x `ViewLoader`). A template sees its data as variables plus `$e`
 * (the Escaper) and `$view` (layouts, sections, partials), in a scope of its own — it cannot reach
 * `$this` or the renderer's locals. A failure discards partial output and throws, rather than 0.x's
 * silent `false`.
 *
 *     $html = $renderer->render('admin/settings', ['title' => $title]);
 */
final class PhpTemplateRenderer implements Renderer
{
    private const RESERVED = ['e', 'view', 'this'];

    /** Layout depth that means a layout includes itself. */
    private const MAX_LAYOUTS = 10;

    /** @param array<string, mixed> $shared Data every view receives (overridden by per-render data). */
    public function __construct(
        private readonly TemplateLocator $locator,
        private readonly Escaper $escaper = new Escaper(),
        private array $shared = []
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->assertKey($key);
        $this->shared[$key] = $value;
    }

    public function exists(string $view): bool
    {
        return $this->locator->locate($view) !== null;
    }

    public function render(string $view, array $data = []): string
    {
        foreach (array_keys($data) as $key) {
            $this->assertKey((string) $key);
        }
        $data += $this->shared;

        [$html, $template] = $this->evaluate($view, $data, []);

        for ($depth = 0; $depth <= self::MAX_LAYOUTS; $depth++) {
            [$layout, $layoutData, $sections] = $template->state();
            if ($layout === null) {
                return $html;
            }
            $sections['content'] = $html;
            [$html, $template] = $this->evaluate($layout, $layoutData + $data, $sections);
        }

        throw ViewException::misuse(sprintf('View "%s" nests more than %d layouts; does a layout use itself?', $view, self::MAX_LAYOUTS));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $sections
     * @return array{string, Template}
     */
    private function evaluate(string $view, array $data, array $sections): array
    {
        $path = $this->locator->locate($view) ?? throw ViewException::notFound($view, $this->locator->searched($view));
        $template = new Template($this, $sections);

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__data, Escaper $e, Template $view): void {
                extract($__data, EXTR_SKIP);
                unset($__data);
                include $__file;
            })($path, $data, $this->escaper, $template);
        } catch (Throwable $t) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $t instanceof ViewException ? $t : ViewException::failed($view, $t);
        }

        if ($template->state()[3] !== []) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw ViewException::misuse(sprintf('View "%s" left section "%s" open; call $view->stop().', $view, $template->state()[3][0]));
        }

        return [(string) ob_get_clean(), $template];
    }

    private function assertKey(string $key): void
    {
        if (in_array($key, self::RESERVED, true) || str_starts_with($key, '__')) {
            throw ViewException::misuse(sprintf('"%s" is reserved in view data (templates get $e and $view).', $key));
        }
    }
}
