---
schema: "wptoolkit-api/1"
title: "CoreServices"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\CoreServices"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/CoreServices.php"
since: "1.0.0"
stability: "stable"
order: 10
summary: "The services every application gets, as lazy singletons — nothing is built until something asks for it. Each can be rebound by a provider's register(), e.g. to swap the HttpClient."
prev: { title: "CopyOwner", path: "CopyOwner.md" }
next: { title: "LibraryTranslations", path: "LibraryTranslations.md" }
up: { title: "Foundation", path: "index.md" }
---

# CoreServices

`final class Codad5\WPToolkit\Foundation\CoreServices`

The services every application gets, as lazy singletons — nothing is built until something asks
for it. Each can be rebound by a provider's register(), e.g. to swap the HttpClient.

Source `src/Foundation/CoreServices.php` · Since 1.0.0

## Methods

### `register()`

```php
public static function register(Application $app, Container $container): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$app` | [`Application`](Application.md) |  |
| `$container` | [`Container`](Container.md) |  |

---

← [Previous: CopyOwner](CopyOwner.md) · [Up: Foundation](index.md) · [Next: LibraryTranslations](LibraryTranslations.md) →
