# 07 — Migration map from 0.x

What happens to each 0.x class. **Stored data does not change** — meta keys, option keys and value
formats are frozen ([ADR-0016](../adr/0016-1-0-reads-0x-data-unchanged.md)); only the PHP API does.
 The **Status** column is updated as each port lands; the consumer
migration guide (Phase 6) is written from this table.

| 0.x class                    | Lines | 1.0 fate                                                                                   | Phase | Status |
| ---------------------------- | ----: | ------------------------------------------------------------------------------------------ | ----- | ------ |
| `Registry`                   |   556 | **Replaced** by `Application` + `Container`                                                | 1     | ✅ `Foundation\Application`, `Container` |
| `Utils/Autoloader`           |   654 | **Replaced** by `bootstrap/autoload.php` (~50 lines) + Composer ([ADR-0014](../adr/0014-composer-is-optional.md)) | 1 | ✅ `bootstrap/autoload.php` |
| `Utils/Config`               |   534 | **Ported** → readonly `Foundation\Config`                                                  | 1     | ✅ `Foundation\Config` (key `text_domain` kept) |
| `Utils/Requirements`         |   129 | **Folded into** `bootstrap/guard.php` ([ADR-0013](../adr/0013-plugins-boot-through-a-syntax-safe-guard.md)) | 1 | ✅ `bootstrap/guard.php` |
| `Utils/Cache`                |   398 | **Ported** → `CacheStore` + adapters                                                       | 2     | ✅ `Contracts\Cache\CacheStore` + adapters |
| `Utils/Debugger`             |   668 | **Replaced** → `Logger` adapters                                                           | 2     | ✅ `Contracts\Log\Logger` + adapters |
| `Utils/APIHelper`            |   564 | **Ported** → `HttpClient` + `ApiClient`                                                    | 2     | ✅ `Http\Client\ApiClient` |
| `Utils/Filesystem`           |  1273 | **Ported, trimmed** → `Filesystem` adapter                                                 | 2     | ✅ `Contracts\Filesystem\Filesystem` + adapters |
| `Utils/Ajax`                 |   777 | **Merged** → `Http\Router` + middleware + `AjaxTransport`                                  | 3     | ✅ `Http\Router`, `Dispatcher`, `AjaxTransport` (file deleted with `legacy/`, Phase 5) |
| `Utils/RestRoute`            |  1002 | **Merged** → same, `RestTransport`                                                         | 3     | ✅ `Http\Transport\RestTransport` (file deleted with `legacy/`, Phase 5) |
| `Utils/InputValidator`       |   581 | **Ported** → `Support\Validation` rule objects                                             | 3     | ✅ `Support\Validation\{Rules, Validator, Sanitizer}` |
| `DB/MetaBox`                 |  1272 | **Rebuilt** on `Field` + `FieldFactory`                                                    | 4     | ✅ `Data\MetaBox` + `Data\Field\*`; **deleted** |
| `DB/Model`                   |  2655 | **Split** → `Entity`, repositories, `Search`, `Admin\Columns`, `Authorize` middleware       | 4     | ✅ `Data\Entity`, `Adapters\Repository\*`, `Data\Search`, `Admin\Columns`, route access rules; **deleted** |
| `Utils/ViewLoader` + `ViewHelper` | 655 + 95 | **Ported** → `View\Renderer` + `TemplateLocator` + `Escaper`                   | 5     | ✅ `View\PhpTemplateRenderer`, `TemplateLocator`, `Escaper`, `Template` |
| `Utils/EnqueueManager`       |  1286 | **Ported** → `Assets\AssetManager`                                                         | 5     | ✅ `Assets\AssetManager`, `Asset`, `JsNamespace` |
| `Utils/Page`                 |  1831 | **Split** → `Admin\Page` + router frontend routes                                          | 5     | ✅ `Admin\Page`, `Admin\Pages`; frontend pages not ported (unused) |
| `Utils/Settings`             |  1210 | **Ported** → `Admin\Settings` on the field system                                          | 5     | ✅ `Admin\Settings\Settings`, `SettingsForm` |
| `Utils/Notification`         |   819 | **Ported** → `Admin\Notice`                                                                | 5     | ✅ `Admin\Notices`, `NoticeType` |
| `Cli/ExampleCommand`         |    43 | **Replaced** by `make:*` commands                                                          | 6     | ☐      |
