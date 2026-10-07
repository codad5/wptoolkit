<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin;

use Closure;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * One admin screen (ports the admin half of 0.x `Page`). Every page names the capability it
 * requires — there is no default — and the page re-checks it before rendering.
 *
 *     Page::top('pau-alumni-manager', __('PAU Alumni Manager', 'pau'), 'manage_options')
 *         ->menuTitle(__('Alumni', 'pau'))->icon('dashicons-groups')->position(30)->view('admin/list');
 *     Page::under('pau-alumni-manager', 'pau-alumni-settings', __('Settings', 'pau'), 'manage_options')->render($fn);
 *     Page::postTypeList('pau-alumni-manager', 'pau-executive', __('Executives', 'pau'), 'edit_posts');
 *     Page::hidden('pau-alumni-view', __('View alumnus', 'pau'), 'manage_options')->view('admin/view');
 *
 * Slugs are used as given, so 0.x admin URLs (`admin.php?page=…`) keep working; start new ones with
 * your plugin's slug.
 */
final class Page
{
    public ?string $menuTitle = null;

    public ?string $icon = null;

    public ?int $position = null;

    /** @var (Closure(): void)|null */
    public ?Closure $render = null;

    public ?string $view = null;

    /** @var array<string, mixed>|(Closure(): array<string, mixed>) */
    public array|Closure $data = [];

    /** @var array<string, array{title: string, content: string}> */
    public array $help = [];

    /**
     * @param string|null $parent Null: a top-level menu. '': registered but not in the menu.
     */
    private function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $capability,
        public readonly ?string $parent,
        public readonly bool $linksElsewhere = false
    ) {
        if ($capability === '') {
            throw new InvalidConfigException(sprintf('Admin page "%s" needs a capability.', $slug));
        }
        if (!$linksElsewhere && preg_match('/^[a-z0-9_\-]+$/', $slug) !== 1) {
            throw new InvalidConfigException(sprintf('Admin page slug "%s" may contain only lowercase letters, digits, "-" and "_".', $slug));
        }
    }

    public static function top(string $slug, string $title, string $capability): self
    {
        return new self($slug, $title, $capability, null);
    }

    public static function under(string $parent, string $slug, string $title, string $capability): self
    {
        return new self($slug, $title, $capability, $parent);
    }

    /**
     * Reachable at `admin.php?page={slug}` but not listed in the menu.
     */
    public static function hidden(string $slug, string $title, string $capability): self
    {
        return new self($slug, $title, $capability, '');
    }

    /**
     * A menu entry that opens a post type's list screen (0.x's `addSubmenuPage($model, …)`).
     */
    public static function postTypeList(string $parent, string $postType, string $title, string $capability): self
    {
        return new self('edit.php?post_type=' . sanitize_key($postType), $title, $capability, $parent, true);
    }

    public function menuTitle(string $menuTitle): self
    {
        $this->menuTitle = $menuTitle;

        return $this;
    }

    /**
     * A dashicon (`dashicons-groups`), an image URL, or a base64 SVG data URI.
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function position(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Render with a callback that prints the page.
     *
     * @param Closure(): void $render
     */
    public function render(Closure $render): self
    {
        $this->render = $render;

        return $this;
    }

    /**
     * Render a view (Phase 5.1) with data — an array, or a closure called only when the page shows.
     *
     * @param array<string, mixed>|(Closure(): array<string, mixed>) $data
     */
    public function view(string $view, array|Closure $data = []): self
    {
        $this->view = $view;
        $this->data = $data;

        return $this;
    }

    /**
     * A help tab on the screen. `$content` is HTML and is passed through wp_kses_post().
     */
    public function help(string $id, string $title, string $content): self
    {
        $this->help[$id] = ['title' => $title, 'content' => $content];

        return $this;
    }
}
