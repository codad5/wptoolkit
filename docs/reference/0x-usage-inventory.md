# 0.x usage inventory

Every 0.x API the known consumers use, so 1.0 covers it and the migration guide can show real
before/after code. Gathered 2026-10-07 by scanning each project's own PHP (excluding `vendor/`,
`wptoolkit/`, `kirki/`, `node_modules/`).

## Classes and calls

| 0.x API | member-directory | example-theme | another-theme | 1.0 equivalent (phase) |
| ------- | --- | ---------------------- | ----------------- | ---------------------- |
| `Config::plugin()` / `Config::theme()` | plugin ×1 | theme ×1 | theme ×1 | `Application::create()` (1) |
| `Registry::registerApp/addMany/get/aliases/factory` | get ×18, others ×1 | get ×2 | get ×2 | container + constructor injection (1) |
| `Autoloader::init()` | ×1 | ×1 | ×1 | Composer or `bootstrap/autoload.php` (1) |
| `Requirements` | ✓ | ✓ | ✓ | the guard (1) |
| `Debugger::info/error/varDump/initFromConfig` | info ×25, error ×18 | varDump ×2 | varDump ×4 | `Logger` adapters (2) |
| `Cache::delete()` | — | ×6 | ×10 | `CacheStore` (2) |
| `APIHelper` (subclassed) | `DirectoryApiHelper` | — | — | `HttpClient` + `ApiClient` (2) |
| `RestRoute::create()` + `->get()` | 7 routes, all public by design | — | — | `Router` + `->public()` (3) |
| `Ajax::create()` + `addAction()` | — | 4 actions, all `'public' => true` | 4 actions, all `'public' => true` | `Router` + `->public()` + `exposeVia('ajax')` (3) |
| `Ajax::getAjaxHelperScriptHandle/Url()` | — | ×8 | ×7 | JS client handle via `Identity::handle()` (3) |
| `Model` (subclassed) | 3 | 4 | 4 | `Entity` + `PostTypeRepository` (4) |
| `MetaBox::create()` | 5 | 4 | 4 | `MetaBox` on `FieldFactory` (4) |
| `Settings::create()` | ✓ | ✓ | ✓ | `Admin\Settings` (5) |
| `EnqueueManager::create()` | — | ✓ | ✓ | `AssetManager` (5) |
| `Page::create()` | ✓ | ✓ | ✓ | `Admin\Page` + router (5) |
| `Notification::create/initGlobal()` | ✓ | — | — | `Admin\Notice` (5) |

## Stored data shapes (must stay readable — ADR-0016)

| Project | Slug / text domain | Post types | Meta key prefix |
| ------- | ------------------ | ---------- | --------------- |
| member-directory | `member-directory` (from `$this->app_slug`) | `event`, `acme-executive`, `acme-partner` | default `{metabox_id}_{post_type}_`, e.g. `executive_role_acme-executive_` |
| acme-theme | `acme-theme` / `acme-theme` | `acme_contact`, `acme_slider`, `acme_movies`, `acme_movie` | custom via `set_prefix(META_PREFIX)`: `_acme_contact_`, `_acme_slider_`, `_acme_movies_`, `_acme_movie_` |
| beta-theme | `beta-theme` / `beta-theme` | `beta_contact`, `beta_movies`, `beta_movie`, `beta_slider` | custom via `set_prefix(META_PREFIX)`: `_beta_contact_`, `_beta_movies_`, `_beta_movie_`, `_beta_slider_` |

Settings options follow `{slug}_{key}`, e.g. `member-directory_api_base_url`,
`acme-theme_reach_api_key`.

## Watch-outs found (feed the migration guide)

- **Consumers query meta keys directly** (`META_PREFIX . 'availability'` in `WP_Query` meta queries,
  `META_PREFIX . 'display_order'` as `meta_key`). Any change to key shapes would silently break
  their queries — ADR-0016 forbids it.
- **member-directory's `/users` and `/settings` routes leaked data** through the open REST default; they are now
  disabled in member-directory. Every remaining member-directory route needs an explicit `->public()` in 1.0.
- **Every Ajax action in both themes is public**, including form submissions — in 1.0 each one
  needs `->public()` *and* should get a rate limit.
- **The `text_domain` key**: consumers pass `'text_domain'`; 0.x mostly reads it, but `Model` reads
  `'textdomain'`, so its strings silently fall back to `default`. 1.0 reads `'text_domain'` only.
- **`Registry::get()` is the most used API in member-directory** (18 calls) — the biggest mechanical change.
- **Both themes carry a git clone of the library**, at different git states — they need the scoped
  standalone zip (ADR-0014).
