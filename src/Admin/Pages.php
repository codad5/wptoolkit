<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin;

use Codad5\WPToolkit\Contracts\View\Renderer;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\HookRegistrar;

/**
 * Registers a plugin's admin pages on `admin_menu`, adds their help tabs when each screen loads,
 * and renders them — after checking the page's capability again.
 */
final class Pages
{
    /** @var array<string, Page> */
    private array $pages = [];

    /** @var array<string, string> slug => hook suffix returned by WordPress */
    private array $hookSuffixes = [];

    private bool $registered = false;

    public function __construct(private readonly HookRegistrar $hooks, private readonly ?Renderer $renderer = null)
    {
    }

    public function add(Page $page): Page
    {
        if (isset($this->pages[$page->slug])) {
            throw new InvalidConfigException(sprintf('Admin page "%s" is already added.', $page->slug));
        }

        return $this->pages[$page->slug] = $page;
    }

    public function register(): void
    {
        if (!$this->registered) {
            $this->registered = true;
            $this->hooks->addAction('admin_menu', [$this, 'addToMenu']);
        }
    }

    /**
     * `admin.php?page={slug}` with query arguments (0.x `getAdminUrl()`).
     *
     * @param array<string, string|int> $args
     */
    public function url(string $slug, array $args = []): string
    {
        $page = $this->pages[$slug] ?? throw new InvalidConfigException(sprintf('There is no admin page "%s".', $slug));
        $base = $page->linksElsewhere ? admin_url($page->slug) : admin_url('admin.php?page=' . rawurlencode($page->slug));

        return $args === [] ? $base : add_query_arg(array_map('rawurlencode', array_map('strval', $args)), $base);
    }

    /**
     * The screen's hook suffix (`toplevel_page_x`), for `Asset::when()`; null before admin_menu.
     */
    public function hookSuffix(string $slug): ?string
    {
        return $this->hookSuffixes[$slug] ?? null;
    }

    /**
     * @internal Hooked to admin_menu. Top-level pages first, so their submenus have a parent.
     */
    public function addToMenu(): void
    {
        $ordered = array_merge(
            array_filter($this->pages, static fn (Page $p): bool => $p->parent === null),
            array_filter($this->pages, static fn (Page $p): bool => $p->parent !== null)
        );

        foreach ($ordered as $page) {
            $callback = $page->linksElsewhere ? '' : fn () => $this->display($page);
            $menuTitle = $page->menuTitle ?? $page->title;

            $suffix = $page->parent === null
                ? add_menu_page($page->title, $menuTitle, $page->capability, $page->slug, $callback, $page->icon ?? '', $page->position)
                : add_submenu_page($page->parent, $page->title, $menuTitle, $page->capability, $page->slug, $callback, $page->position);

            if (is_string($suffix) && $suffix !== '') {
                $this->hookSuffixes[$page->slug] = $suffix;
                if ($page->help !== []) {
                    $this->hooks->addAction('load-' . $suffix, fn () => $this->addHelp($page));
                }
            }
        }
    }

    /**
     * @internal The menu callback.
     */
    public function display(Page $page): void
    {
        if (!current_user_can($page->capability)) {
            wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'wptoolkit'), '', ['response' => 403]);
        }

        if ($page->render !== null) {
            ($page->render)();
            return;
        }

        if ($page->view !== null) {
            if ($this->renderer === null) {
                throw new InvalidConfigException(sprintf('Admin page "%s" uses a view but no Renderer was given.', $page->slug));
            }
            $data = $page->data instanceof \Closure ? ($page->data)() : $page->data;
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered template output, escaped in the template.
            echo $this->renderer->render($page->view, $data);
        }
    }

    private function addHelp(Page $page): void
    {
        $screen = get_current_screen();
        if ($screen === null) {
            return;
        }

        foreach ($page->help as $id => $tab) {
            $screen->add_help_tab(['id' => $id, 'title' => $tab['title'], 'content' => wp_kses_post($tab['content'])]);
        }
    }
}
