# Admin and front end

## Settings

```php
$settings = new Settings([
    $f->url('api_base_url')->label(__('API base URL', 'my-books'))->required()->with('group', 'api'),
    $f->password('api_key')->label(__('API key', 'my-books'))->sensitive()->with('encrypt', true)->with('group', 'api'),
    $f->number('per_page')->label(__('Items per page', 'my-books'))->default(20)->rules('min:5|max:100'),
], $fieldTypes, $identity, new SecretBox(wp_salt('auth')), $logger);

$settings->get('per_page');     // 20 until saved
$settings->set('per_page', 50); // validates first; returns error messages
$settings->all();               // every setting EXCEPT sensitive ones
$settings->get('api_key');      // a secret only moves when code names it
```

One option per setting under `{slug}_{name}`.

**Sensitive fields** ([ADR-0021](../adr/0021-sensitive-settings-are-read-only-by-name.md)):
- they are left out of `all()`, so out of every response built from it;
- `forScript()` refuses them;
- the logger redacts them;
- the form never prints them back.

Encryption needs a stable key; if WordPress's salts change, the value reads as missing and the form
asks for it again. `$settings->upcast('mode', fn ($old) => …)` turns an old stored shape into the
current one on read.

The form uses the Settings API:

```php
$form = (new SettingsForm($settings, $fieldTypes, $identity))->section('api', __('API', 'my-books'));
$form->register($hooks, 'my-books-settings');
// in the page: $form->render('my-books-settings');
```

A blank secret keeps the saved one; a "clear" checkbox removes it; an invalid value keeps the old one
and shows why.

## Admin pages

```php
$pages->add(Page::top('my-books', __('Books', 'my-books'), 'edit_posts')
    ->icon('dashicons-book')->view('admin/dashboard', fn () => ['total' => wp_count_posts('book')->publish])
    ->help('about', __('About', 'my-books'), '<p>…</p>'));
$pages->add(Page::under('my-books', 'my-books-settings', __('Settings', 'my-books'), 'manage_options')
    ->render(fn () => $form->render('my-books-settings')));
$pages->add(Page::postTypeList('my-books', 'book', __('All books', 'my-books'), 'edit_posts'));
$pages->add(Page::hidden('my-books-import', __('Import', 'my-books'), 'manage_options')->view('admin/import'));

$pages->url('my-books-import', ['step' => 2]);
```

Every page names its capability and re-checks it before rendering.

## Notices

```php
$notices->flash(__('Import finished.', 'my-books'));                                  // once, this user
$notices->persistent('api-key-missing', __('Add your API key.', 'my-books'), NoticeType::Warning); // until dismissed
```

Stored per user. Errors are announced with `role="alert"`, the rest with `role="status"`.

## Views

Templates live in `views/` (or the paths in the `views` config); a theme overrides one by copying it
to `{theme}/{slug}/`.

```php
echo $renderer->render('admin/dashboard', ['total' => 12]);
```

```php
<?php $view->layout('layouts/admin', ['title' => __('Books', 'my-books')]); ?>
<p><?php $e->html(sprintf(_n('%d book', '%d books', $total, 'my-books'), $total)); ?></p>
<a href="<?php $e->url($link); ?>"><?php $e->html($label); ?></a>
```

`$e` prints escaped values: `html`, `attr`, `url`, `textarea`, `js`, `kses`, `json`.

`$view` composes templates: `layout()`, `start()`/`stop()` + `section()`, and `insert()` for
partials.

A raw `echo $value` in a template fails the PHPCS check.

## Assets

```php
$assets->script('admin', 'build/admin.js')
    ->in(Asset::ADMIN)
    ->when(fn (string $hook) => $hook === $pages->hookSuffix('my-books'))
    ->with($settings->forScript('per_page'));
$assets->style('front', 'build/front.css');
```

- **When:** declare assets any time. They reach WordPress only inside the enqueue hooks.
- **Dependencies:** a `build/x.asset.php` manifest supplies dependencies and version.
- **Translations:** scripts that depend on `wp-i18n` get their translations automatically.
- **RTL:** `front-rtl.css` replaces `front.css` on right-to-left sites.
- **Data:** values passed with `with()` land in `window.wptoolkit['my-books']`, alongside the `toolkitVersion`. That namespace can't be replaced by another script.

## Front-end pages

```php
$public->page('library', __('Library', 'my-books'), 'front/library', fn () => [
    'books' => $books->query(Query::create()->orderBy('title')),
])->public();
```

Each one is a router route with a rewrite rule: same access rules, validation and rate limits as the
API. Rules flush only when they change. The view renders inside the theme (classic or block), with the
page title and a body class.

## Shortcodes

```php
public function boot(Shortcodes $shortcodes, AssetManager $assets, CoinService $coins): void
{
    $assets->script('widget', 'build/widget.js')->onDemand();      // not loaded on every page

    $shortcodes->add('widget', fn (array $a): array => ['coins' => $coins->top($a['count'])])
        ->public()
        ->attributes(['count' => ['type' => 'int', 'default' => 10, 'rules' => 'integer|min:1|max:50']])
        ->view('front/widget')
        ->assets('widget');                                         // enqueued only where it renders
}
```

This registers `[my_books_widget count="5"]`. What you get over a bare `add_shortcode()`:

- **A prefixed tag** from your slug. A plugin that already ships a tag (it is saved in users' posts) keeps it:
  `$shortcodes->add('widget', $handler, tag: 'ucwp_widget')`. A tag someone else registered is refused.
- **Declared attributes**, validated and typed like route args; anything undeclared never reaches your
  handler. A bad value falls back to its default and is logged by attribute name (a visitor can't fix a post).
- **An access rule you must state** — `->public()`, `->loggedIn()` or `->can('cap')`. Without one the
  shortcode prints nothing, and in development it fails when the app boots.
- **Return, don't echo.** With `->view()` return the view's data (escaped in the template); without a
  view return an HTML string you have escaped. Enclosed content arrives as the second argument, raw.
- **Lazy assets** with `->assets()` on assets declared `->onDemand()`.
- **No white screen:** a handler that throws prints nothing and is logged (rethrown in development).

To get a shortcode's output in code, call `$shortcodes->render('widget', ['count' => 5])`. Never build a
`[tag attr=…]` string from request data and run it with `do_shortcode()` — that lets a visitor run any
shortcode on the site. `wp my-books make:shortcode Widget` writes a handler class to start from.
