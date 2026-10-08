# Migrating from WPToolkit 0.x to 1.0

1.0 is a rebuild of 0.x: the PHP API changed, **your stored data did not**. Meta keys, option keys
and value formats are exactly 0.x's ([ADR-0016](../adr/0016-1-0-reads-0x-data-unchanged.md)), so
there is **no data migration** — upgrade the code, deploy, and existing posts, meta and settings read
the same. You can roll back to 0.x afterwards: 1.0 writes the formats 0.x reads.

The before/after code below is from the real 0.x consumers — `pau-alumni-manager` (a plugin) and
the `silverbird`/`nile` themes. Where a class went is listed in the
[migration map](../architecture/07-migration-from-0x.md).

---

## Before you start

1. **Pin your current version.** Change `"codad5/wptoolkit": "dev-main"` to the last 0.x tag
   (`"0.2.*"`), deploy, and confirm nothing moved. 1.0 will land on `main`; `dev-main` would pull it
   in unannounced.
2. **Requirements:** PHP 8.1+, WordPress 6.4+.
3. **Check out a branch.** Work through the steps below in order; each leaves the site working.

## 1. Install and boot

**0.x** (pau): the library's autoloader, a `Config`, a `Requirements` check, `Registry`.

```php
require_once __DIR__ . '/vendor/codad5/wptoolkit/autoloader.php';
use Codad5\WPToolkit\Utils\{Config, Autoloader, Requirements, Registry};

Autoloader::init(['PAU\\' => __DIR__ . '/src/']);
$config = Config::plugin('pau-alumni-manager', __FILE__, ['text_domain' => 'pau-alumni-manager']);
Registry::registerApp($config);
```

**1.0:** the main file goes through the guard (it parses on PHP 5.6, so an old server shows a notice
instead of crashing), then builds one `Application` with service providers.

```php
<?php
/**
 * Plugin Name: PAU Alumni Manager
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

call_user_func(require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php', __FILE__, array(
    'php' => '8.1',
    'wp' => '6.4',
    'toolkit' => '^1.0',
    'name' => 'PAU Alumni Manager',
), function () {
    \Codad5\WPToolkit\Foundation\Application::create(__FILE__, [
        'slug' => 'pau-alumni-manager',          // keep the 0.x slug: option keys are built from it
        'text_domain' => 'pau-alumni-manager',
    ])
        ->providers([\PAU\AppServiceProvider::class, \PAU\AdminServiceProvider::class])
        ->boot();
});
```

- **Keep your 0.x slug.** Option keys (`{slug}_{key}`) and REST namespaces come from it.
- **Themes** pass `'type' => 'theme'` and boot from `functions.php` the same way.
- **No Composer?** Ship the standalone zip (built with `wptoolkit-build`), scoped to your own
  namespace — see [scoping and coexistence](scoping-and-coexistence.md). The themes that carry a
  git clone of the library must switch to this: two clones at different versions on one site is
  exactly what 1.0's coexistence rules exist to prevent.

## 2. `Registry::get()` → constructor injection

pau calls `Registry::get()` 18 times. In 1.0 there is no global registry: a provider's `boot()` asks
for what it needs, and the container builds it.

```php
// 0.x
$settings = Registry::get('pau-alumni-manager', 'settings');
$settings->get('api_key');

// 1.0
final class AdminServiceProvider extends ServiceProvider
{
    public function boot(Settings $settings, Pages $pages, Router $router): void { … }
}
```

Register your own services in `register()` (`$this->app->container()->singleton(Foo::class, …)`);
any class with typed constructor parameters is built automatically.

## 3. `Debugger` → `Logger`

```php
// 0.x
Debugger::info('Fetched alumni', ['count' => $count]);
Debugger::error('API call failed', ['error' => $e->getMessage()]);

// 1.0 — inject Contracts\Log\Logger
$this->logger->info('Fetched {count} alumni', ['count' => $count]);
$this->logger->error('API call failed: {exception}', ['exception' => $e]);
```

Values under keys such as `api_key`, `token` or `password` are redacted automatically; sensitive
settings are redacted by name. `varDump()` has no replacement — use the Query Monitor log channel
(`'log' => ['channels' => ['query_monitor']]`).

## 4. `Cache` → `CacheStore`

```php
// 0.x (silverbird, 6 calls)
Cache::delete('movies_now_showing');

// 1.0 — inject Contracts\Cache\CacheStore (keys are prefixed with your slug for you)
$this->cache->delete('movies_now_showing');
$movies = $this->cache->remember('movies_now_showing', 600, fn () => $this->loadMovies());
```

## 5. `RestRoute` and `Ajax` → one `Router`

**Every route must say who may call it** — there is no open default
([ADR-0008](../adr/0008-deny-by-default-one-http-pipeline.md)). A route without a rule fails to
register in development and answers 403 in production. This is the change that matters most:
pau's `/users` and `/settings` routes leaked data through 0.x's open default.

**REST (pau's gallery endpoint):**

```php
// 0.x
$api = RestRoute::create($this->config, ['v1', 'v2'], 'v1');
$api->get('v1', '/gallery', function () { … return new \WP_Error('no_gallery_page', '…', ['status' => 404]); });

// 1.0 — same URL: /wp-json/pau-alumni-manager/v1/gallery
$router->get('gallery', [GalleryController::class, 'show'])->public();

final class GalleryController
{
    public function show(Settings $settings): array
    {
        $pageId = (int) $settings->get('gallery_page_id');
        if ($pageId === 0) {
            throw HttpError::notFound(__('No gallery page has been configured.', 'pau-alumni-manager'));
        }
        return ['content' => apply_filters('the_content', get_post_field('post_content', $pageId))];
    }
}
```

**Ajax (silverbird's movie search):**

```php
// 0.x — 'public' => true, hand-written sanitize callbacks
$this->ajax->addAction('search_movies', [$this, 'handleMovieSearch'], [
    'public' => true,
    'args' => [
        'search_term' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        'availability' => ['type' => 'string', 'sanitize_callback' => 'sanitize_key'],
        'limit' => ['type' => 'integer', 'sanitize_callback' => 'intval'],
    ],
]);

// 1.0 — validated before your code runs, rate limited, same controller for REST and Ajax
$router->get('movies/search', [MovieController::class, 'search'])
    ->public()
    ->rateLimit(30, perSeconds: 60)
    ->args([
        'search_term' => ['rules' => 'max:100'],
        'availability' => ['rules' => 'in:now_showing,coming_soon', 'type' => 'key'],
        'limit' => ['rules' => 'integer|min:1|max:50', 'type' => 'int', 'default' => 12],
    ])
    ->exposeVia('rest', 'ajax');
```

- Controllers read input with `$request->input('search_term')` — only declared args arrive.
- Errors are real HTTP statuses (`HttpError::notFound()`, a 422 for invalid input), not
  `200 {success: false}`.
- **Front end:** replace `Ajax::getAjaxHelperScriptHandle()` and its `wptoolkitAjax` global with the
  JS client: `$assets->client($router)`, then in JavaScript
  `window.wptoolkit['silverbird-theme'].api.call('movies_search', { search_term: 'dune' })`.
- **Public form submissions** (contact forms) should get a rate limit and a stricter validation rule
  set — 0.x exposed all of them without either.

## 6. `APIHelper` → `ApiClient`

```php
// 0.x
class PAUAlumniAPIHelper extends APIHelper { … }

// 1.0 — compose instead of extend; retries, caching and logging are built in
$api = new ApiClient($http, $settings->get('api_base_url'), Auth::bearer((string) $settings->get('api_key')), cache: $cache, logger: $logger);
$alumni = $api->getJson('alumni', ['page' => 1], cacheTtl: 300);
```

## 7. `Model` and `MetaBox` → entities, repositories and meta boxes

0.x `Model` did everything in one class; 1.0 splits it. **Stored keys stay the same**, as long as you
keep the meta box id (or the custom prefix).

**silverbird's movies** (custom prefix `_silverbird_movies_`, a gallery of attachment IDs):

```php
// 1.0
#[PostType('silverbird_movies', box: 'movie_details', metaPrefix: '_silverbird_movies_',
    columns: ['title' => 'post_title'], public: true)]
final class Movie extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [
            $f->text('title')->required(),
            $f->select('availability', ['now_showing' => __('Now showing', 'silverbird'), 'coming_soon' => __('Coming soon', 'silverbird')]),
            $f->media('gallery')->multiple(),          // still one meta row per attachment ID
        ];
    }
}

// In a provider: post type + edit-screen box (same keys) + list columns
$box = $registrar->entity(Movie::class, __('Movie details', 'silverbird'));
(new Columns($box, [Column::field('availability')->sortable()]))->register($hooks);

// Reading and writing
$movies = $repositories->for(Movie::class);
$showing = $movies->query(Query::create()->where('availability', 'now_showing')->orderBy('title'));
```

- **Post columns are opt-in.** `title`, `content`, `status`… are post meta unless `columns:` maps
  them. pau's executives have a meta field called `title`: leave it unmapped and it stays where 0.x
  put it.
- **`'wp_media'` still works** as a type name. Single media values now read as an attachment ID, not
  a URL — call `wp_get_attachment_url($id)` where you printed it.
- **Checkboxes** read 0.x's `'on'` as `true`; 1.0 writes `'1'`/`'0'`.
- **Numbers** keep negative values (0.x's `absint` dropped the sign).
- **Search:** `Model::search()` → `Data\Search\Search`, which now ORs title, meta and terms (0.x
  required all of them) and only searches fields you list.
- **Queries on your own meta keys** (`META_PREFIX . 'availability'` in a `WP_Query`) keep working —
  the keys didn't change. Moving them to `Query` is optional.
- **Quick edit** is `->quickEdit()` on a field, shown in its column; it no longer uses a public Ajax
  endpoint.

## 8. `Settings` → `Admin\Settings`

```php
// 0.x (pau)
$settings = Settings::create([
    'api_base_url' => ['type' => 'url', 'label' => __('API Base URL', 'pau-alumni-manager'), 'group' => 'api'],
    'api_key' => ['type' => 'password', 'label' => __('API Key', 'pau-alumni-manager'), 'group' => 'api'],
    'items_per_page' => ['type' => 'number', 'default' => 20],
], $this->config);

// 1.0 — same option keys (pau-alumni-manager_api_key …), so saved values carry over
$settings = new Settings([
    $f->url('api_base_url')->label(__('API Base URL', 'pau-alumni-manager'))->required()->with('group', 'api'),
    $f->password('api_key')->label(__('API Key', 'pau-alumni-manager'))->sensitive()->with('group', 'api'),
    $f->number('items_per_page')->default(20)->rules('min:5|max:100'),
], $types, $identity, logger: $logger);
```

- **`getAll()` → `all()`, which leaves sensitive settings out.** Read a secret by name:
  `$settings->get('api_key')`. Mark API keys, tokens and webhook secrets `->sensitive()`.
- Rendering the form: `SettingsForm` (Settings API), in an admin page — see step 9.

## 9. `Page`, `Notification` and views

```php
// 0.x (pau)
$page->addMenuPage('pau-alumni-manager', ['page_title' => …, 'capability' => 'manage_options', 'callback' => [$this, 'renderAlumniList'], 'icon' => 'dashicons-groups', 'position' => 30]);
$page->addSubmenuPage(ExecutiveModel::get_instance(), ['parent_slug' => 'pau-alumni-manager', …]);

// 1.0 — same slugs, so admin URLs don't change
$pages->add(Page::top('pau-alumni-manager', __('PAU Alumni Manager', 'pau-alumni-manager'), 'manage_options')
    ->icon('dashicons-groups')->position(30)->render(fn () => AlumniListPage::render()));
$pages->add(Page::postTypeList('pau-alumni-manager', 'pau-executive', __('Executives', 'pau-alumni-manager'), 'manage_options'));
$pages->url('pau-alumni-settings');            // was $page->getAdminUrl(…)

// Notices
$notices->flash(__('Settings saved.', 'pau-alumni-manager'));
```

Templates move to `views/` and print through `$e`: `<?php $e->html($title); ?>`. Themes override them
under `{theme}/{slug}/`. `addFrontendPage()` becomes `PublicPages::page()`.

## 10. `EnqueueManager` → `AssetManager`

```php
$assets->script('movies', 'build/movies.js')->in(Asset::FRONT)->with(['perPage' => 12]);
$assets->style('movies', 'build/movies.css');            // build/movies-rtl.css is used on RTL sites
```

Declare assets whenever you like; they reach WordPress inside the enqueue hooks. Data lands in
`window.wptoolkit['your-slug']` instead of a global of your own.

---

## Watch out for

| 0.x habit | In 1.0 |
| --- | --- |
| REST routes with no permission callback | Add `->public()`, `->loggedIn()`, `->can()` or `->authorize()` to **every** route |
| Ajax actions with `'public' => true` | `->public()` plus a `->rateLimit()` for anything that writes |
| `__()` in a constructor or at `plugins_loaded` | Translate in a provider's `boot()` (runs on `init`) |
| `Registry::get()` | Constructor or `boot()` injection |
| A git clone of the library in a theme | The scoped standalone zip |
| `"dev-main"` | `"^1.0"` |
| `'textdomain'` in config (only `Model` read it) | `'text_domain'` |
| `$settings->getAll()` returned secrets | `all()` omits sensitive fields; name them with `get()` |
| Media fields read as URLs | They read as attachment IDs |

## Checklist

- [ ] Version constraint pinned, PHP 8.1 and WP 6.4 confirmed on every environment.
- [ ] Main file boots through the guard; the slug is unchanged.
- [ ] Every route and Ajax action has an access rule; public ones that write are rate limited.
- [ ] Meta box ids / prefixes and settings keys unchanged; sensitive settings marked.
- [ ] `wp {your-slug} routes:list` shows no route as "NONE (refused)".
- [ ] Staging soak, then production. Rolling back to the pinned 0.x tag stays possible.
