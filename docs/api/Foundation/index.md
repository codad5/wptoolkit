---
schema: "wptoolkit-api/1"
title: "Foundation"
type: "namespace"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
order: 1
summary: "The kernel: one Application per plugin, its container and providers, the hooks it adds, and the prefixed names (Identity) everything else is built from."
prev: { title: "API reference", path: "../index.md" }
next: { title: "Application", path: "Application.md" }
up: { title: "API reference", path: "../index.md" }
---

# Foundation

The kernel: one Application per plugin, its container and providers, the hooks it adds, and the prefixed names (Identity) everything else is built from.

| Page | Kind | Description |
| --- | --- | --- |
| [Application](Application.md) | class | One consuming plugin or theme. Owns its container, its hooks and its providers; nothing is static, so two plugins never share state (ADR-0007). |
| [ServiceProvider](ServiceProvider.md) | class | Groups the wiring for one feature. |
| [Identity](Identity.md) | class | Every name the library writes into WordPress's global namespace, derived from the consumer's slug, so two plugins — or two copies of WPToolkit — never collide (ADR-0005, ADR-0006). |
| [Config](Config.md) | class | Immutable configuration for one plugin or theme (replaces 0.x `Utils\Config`). |
| [HookRegistrar](HookRegistrar.md) | class | Adds WordPress actions and filters for one plugin and remembers every one, so the plugin can list them (`hooks:list`) and remove all of them on deactivation. Replaces 0.x's `add_tracked_action()`. |
| [Container](Container.md) | class | The library's own container (ADR-0007): bindings, singletons, constructor autowiring, method injection, and an error that names the cycle when entries depend on each other. |
| [Coexistence](Coexistence.md) | class | The coexistence ledger: every copy of WPToolkit loaded on the site records itself here, so a plugin can see which copies exist (`toolkit:info`, diagnostics) and explain a conflict. |
| [CopyOwner](CopyOwner.md) | class | Works out which plugin, must-use plugin or theme a WPToolkit copy was loaded from, so a conflict message can name it instead of leaving the administrator to decode a path. |
| [CoreServices](CoreServices.md) | class | The services every application gets, as lazy singletons — nothing is built until something asks for it. Each can be rebound by a provider's register(), e.g. to swap the HttpClient. |
| [LibraryTranslations](LibraryTranslations.md) | class | Loads translations for the library's own strings (text domain `wptoolkit`) from the package's `languages/` directory — wherever the package lives, including a consumer's `vendor/` or a scoped copy (ADR-0006, ADR-0019). |
| [Psr11Bridge](Psr11Bridge.md) | class | Exposes the application container as PSR-11, for libraries that resolve services from one. |
| [ToolkitCompatibility](ToolkitCompatibility.md) | class | Checks the consumer's `requires_toolkit` constraint against the WPToolkit copy that is actually loaded, and explains a conflict in terms an administrator can act on (ADR-0005, ADR-0020). |

---

← [Previous: API reference](../index.md) · [Up: API reference](../index.md) · [Next: Application](Application.md) →
