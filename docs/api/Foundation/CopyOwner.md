---
schema: "wptoolkit-api/1"
title: "CopyOwner"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\CopyOwner"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/CopyOwner.php"
since: "1.0.0"
stability: "stable"
order: 9
summary: "Works out which plugin, must-use plugin or theme a WPToolkit copy was loaded from, so a conflict message can name it instead of leaving the administrator to decode a path."
prev: { title: "Coexistence", path: "Coexistence.md" }
next: { title: "CoreServices", path: "CoreServices.md" }
up: { title: "Foundation", path: "index.md" }
---

# CopyOwner

`final class Codad5\WPToolkit\Foundation\CopyOwner`

Works out which plugin, must-use plugin or theme a WPToolkit copy was loaded from, so a conflict
message can name it instead of leaving the administrator to decode a path.

Call at render time (admin requests): it may read plugin headers.

Source `src/Foundation/CopyOwner.php` · Since 1.0.0

## Methods

### `of()`

```php
public static function of(string $path): ?array;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |

**Returns** `array{type: 'plugin'\|'mu-plugin'\|'theme', name: string, directory: string}\|null` — Null when the path isn't inside a plugin, must-use plugin or theme directory.

---

← [Previous: Coexistence](Coexistence.md) · [Up: Foundation](index.md) · [Next: CoreServices](CoreServices.md) →
