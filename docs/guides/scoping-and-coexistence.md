# Scoping WPToolkit, so your plugin can live next to any other

Two plugins on one site may bundle two different versions of WPToolkit. PHP can load a class name
only once, so **unscoped** copies fight: the first plugin WordPress loads wins, and the other runs
against code it was never tested with. **Scoping** renames your copy's namespace
(`Codad5\WPToolkit` → `MyPlugin\WPToolkit`), so each plugin runs its own copy.
([ADR-0005](../adr/0005-multiple-copies-coexist-via-scoping.md))

Scoping is strongly recommended. If you skip it, the guard still protects the site: an incompatible
plugin is refused at activation, or stays paused with a notice naming the plugin whose copy won —
it never crashes ([ADR-0020](../adr/0020-incompatible-unscoped-copies-refuse-activation.md)).

---

## Path A — without Composer (standalone zip)

1. Download the standalone zip from the GitHub release and unzip it into your plugin, e.g.
   `my-plugin/lib/wptoolkit/`.
2. Scope it — needs only the PHP CLI:

   ```bash
   php lib/wptoolkit/bin/scope.php "MyPlugin\WPToolkit" lib/wptoolkit
   ```

   It rewrites every namespace and class reference in the copy's `src/`, `legacy/` and
   `bootstrap/`, and refuses to run twice. Re-run it on each library update (after replacing the
   folder).
3. Start your plugin through the guard (main plugin file — keep it PHP 5.6-compatible):

   ```php
   <?php
   /**
    * Plugin Name: My Plugin
    * Requires PHP: 8.1
    */
   call_user_func(require __DIR__ . '/lib/wptoolkit/bootstrap/guard.php', __FILE__, array(
       'php'      => '8.1',
       'wp'       => '6.4',
       'toolkit'  => '^1.0',
       'autoload' => 'standalone',
   ), function () {
       require __DIR__ . '/src/boot.php';
   });
   ```

4. In `src/boot.php` use your scoped namespace:

   ```php
   \MyPlugin\WPToolkit\Foundation\Application::create(dirname(__DIR__) . '/my-plugin.php', [
       'slug' => 'my-plugin',
       'requires_toolkit' => '^1.0',
   ])->providers([...])->boot();
   ```

## Path B — with Composer and Strauss

[Strauss](https://github.com/BrianHenryIE/strauss) copies your dependencies into a folder and
prefixes their namespaces. A typical configuration in your plugin's `composer.json`:

```json
{
    "require": { "codad5/wptoolkit": "^1.0" },
    "require-dev": { "brianhenryie/strauss": "*" },
    "extra": {
        "strauss": {
            "target_directory": "vendor-prefixed",
            "namespace_prefix": "MyPlugin\\Vendor\\",
            "packages": ["codad5/wptoolkit"],
            "delete_vendor_packages": false
        }
    },
    "scripts": {
        "post-install-cmd": ["vendor/bin/strauss"],
        "post-update-cmd": ["vendor/bin/strauss"]
    }
}
```

Your classes then live under `MyPlugin\Vendor\Codad5\WPToolkit\…`. Strauss copies the files a
package autoloads, so require the guard from the original package and point it at the prefixed
copy with `toolkit_path`:

```php
call_user_func(require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php', __FILE__, array(
    'php'          => '8.1',
    'toolkit'      => '^1.0',
    'toolkit_path' => __DIR__ . '/vendor-prefixed/codad5/wptoolkit',
    'autoload'     => __DIR__ . '/vendor-prefixed/autoload.php',
), function () {
    require __DIR__ . '/src/boot.php';
});
```

> Check your Strauss version's documentation for the exact keys; the library's own CI proves
> scoping with `bin/scope.php` (Path A). A Strauss-built example plugin is planned for Phase 6.

## What scoping does *not* change

WordPress-level names — hooks, options, tables, REST namespaces, script handles — come from your
plugin's **slug**, not from the namespace ([ADR-0006](../adr/0006-library-text-domain-and-consumer-prefixes.md)),
so two scoped copies never collide there either. Your stored data keeps the same keys whether or not
you scope ([ADR-0016](../adr/0016-1-0-reads-0x-data-unchanged.md)).

## Checking what's loaded

Every copy records itself in the coexistence ledger. From WP-CLI (Phase 6) `wp {slug} toolkit:info`
lists every copy on the site with its version, namespace and path.
