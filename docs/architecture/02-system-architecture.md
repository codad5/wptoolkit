# 02 — System architecture

## Layers

```
┌──────────────────────────── Consumer plugin ────────────────────────────┐
│ main file (guard) · ServiceProviders · Controllers · Entities · Views   │
└──────────────▲──────────────────────────────────────────▲───────────────┘
               │ uses                                     │ extends
┌──────────────┴────────────── WPToolkit ─────────────────┴───────────────┐
│ bootstrap/  guard.php (PHP 5.6 syntax) · autoload.php (standalone)      │
│ Foundation  Application · Container · ServiceProvider · HookRegistrar   │
│             Config (readonly) · Identity · Lifecycle · Coexistence      │
│ Http        Request · Response · Router · Route · Middleware · Transports│
│ Data        Field + FieldFactory · MetaBox · Entity · Repository        │
│             QueryBuilder · Search                                       │
│ View        Renderer · TemplateLocator · Escaper                        │
│ Admin       Page · Settings · Notice · Columns                          │
│ Assets      AssetManager · Asset                                        │
│ Support     Validation · Sanitization · I18n · Str · Arr                │
├──────────────────────────────── Contracts ──────────────────────────────┤
│ Container · CacheStore · Logger · HttpClient · RateLimiterStore         │
│ Filesystem · Clock · Repository · Renderer · Middleware                 │
├──────────────────────────────── Adapters ───────────────────────────────┤
│ Cache: Transient · ObjectCache · Array · Null    Log: ErrorLog · QueryMonitor · BrowserConsole · Null │
│ Http: WpHttp · Fake    Data: PostType · Options · CustomTable           │
│ View: PhpTemplate      I18n: Wp · Array    Bridges: Psr3 · Psr11 · Psr16 │
└──────────────────────────────────────────────────────────────────────────┘
                                 WordPress core
```

**Dependency rule:** arrows point down. `Contracts` depends on nothing. `Adapters` depend on
`Contracts` and WordPress. Domain layers (`Http`, `Data`, `View`, `Admin`, `Assets`) depend on
`Contracts` and `Support`, never on `Adapters` directly — the container hands them adapters.
Enforced by PHPStan rules (Phase 1). See [.claude/rules/10-architecture-boundaries.md](../../.claude/rules/10-architecture-boundaries.md).

## Source layout

```
bootstrap/    guard.php, autoload.php
bin/          scope.php
src/          Foundation/ Contracts/ Adapters/ Http/ Data/ View/ Admin/ Assets/ Frontend/ Support/
languages/    wptoolkit.pot, *.mo, *.json
resources/    views/, js/, css/
tests/        Unit/ Contract/ Integration/ E2E/ Fixtures/
examples/     todo-plugin/, scoped-plugin/
docs/         architecture/ adr/ phases/ reference/ guides/
```

## Patterns, and where each earns its place

| Pattern                  | Where                                                                 | Why here                                                     |
| ------------------------ | --------------------------------------------------------------------- | ------------------------------------------------------------ |
| Adapter                  | every contract in `Contracts/`                                        | ≥ 2 real backends or a needed test double ([ADR-0004](../adr/0004-ports-and-adapters-at-real-seams.md)) |
| Factory                  | `FieldFactory`, `CacheFactory`, `RepositoryFactory`                   | New field types / drivers / storage plug in without editing core |
| Named constructors       | `Config::plugin()`, `Route::get()`, `$fields->text()`                 | Readable creation without a factory class                    |
| Dependency injection     | `Application` + `Container`                                           | Replaces static `Registry` and singletons ([ADR-0007](../adr/0007-own-container-no-static-registry.md)) |
| Service provider         | `register()` / `boot()` per feature                                   | Groups wiring; lazy features                                 |
| Strategy                 | validation rules, sanitizers, search scorers, rate-limit windows      | Swap behaviour per field/route without subclassing           |
| Chain of responsibility  | middleware pipeline                                                   | One security pipeline for Ajax and REST ([ADR-0008](../adr/0008-deny-by-default-one-http-pipeline.md)) |
| Repository               | entity persistence                                                    | Storage independent of the domain ([ADR-0009](../adr/0009-entity-and-repository-not-active-record.md)) |
| Builder                  | `Route`, `MetaBox`, `Page`                                            | The fluent style consumers already like; produces immutable definitions |
| Value object             | `Config`, `Request`, `Field` definitions                              | Readonly, so state can't drift                               |
| Null object              | `NullLogger`, `NullStore`                                             | No `if ($logger)` everywhere                                 |
| Observer                 | WordPress hooks via `HookRegistrar`                                   | The platform's own model, made trackable and removable       |

**Avoided:** Singleton, service locator in domain code, static facades, interface-per-class.

## What a plugin looks like

```php
// src/boot.php — modern syntax is fine here; the guard has already checked the environment
use Codad5\WPToolkit\Foundation\Application;

Application::create(MY_PLUGIN_FILE, [
    'slug'             => 'my-plugin',
    'text_domain'       => 'my-plugin',
    'requires_toolkit' => '^1.0',
    'cache'            => ['driver' => 'object'],
])
->providers([
    MyPlugin\Providers\BookServiceProvider::class,
    MyPlugin\Providers\AdminServiceProvider::class,
])
->boot();
```

```php
final class BookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookRepository::class);
    }

    public function boot(Router $router): void
    {
        $router->get('books/search', [BookController::class, 'search'])
               ->can('read')                     // omit this and registration fails
               ->rateLimit(30, perSeconds: 60)
               ->exposeVia('rest', 'ajax');      // one controller, two transports
    }
}
```

```php
#[PostType('book', public: true, columns: ['title' => 'post_title'])]
final class Book extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [
            $f->text('isbn')->label(__('ISBN', 'my-plugin'))->required(),
            $f->number('pages')->label(__('Pages', 'my-plugin'))->rules('min:1'),
        ];
    }
}
```
