---
schema: "wptoolkit-api/1"
title: "LibraryTranslations"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\LibraryTranslations"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/LibraryTranslations.php"
since: "1.0.0"
stability: "stable"
order: 11
summary: "Loads translations for the library's own strings (text domain `wptoolkit`) from the package's `languages/` directory — wherever the package lives, including a consumer's `vendor/` or a scoped copy (ADR-0006, ADR-0019)."
related: { adr: [6, 19] }
prev: { title: "CoreServices", path: "CoreServices.md" }
next: { title: "Psr11Bridge", path: "Psr11Bridge.md" }
up: { title: "Foundation", path: "index.md" }
---

# LibraryTranslations

`final class Codad5\WPToolkit\Foundation\LibraryTranslations`

Loads translations for the library's own strings (text domain `wptoolkit`) from the package's
`languages/` directory — wherever the package lives, including a consumer's `vendor/` or a
scoped copy (ADR-0006, ADR-0019).

Called on `init`, never earlier, so WordPress 6.7's "translation loading triggered too early"
notice can't fire. When another copy of WPToolkit already loaded `wptoolkit`, this one doesn't
load again: identical source strings translate identically, which is the cost ADR-0006 accepts.

Source `src/Foundation/LibraryTranslations.php` · Since 1.0.0 · Decisions: [ADR-0006](../../adr/0006-library-text-domain-and-consumer-prefixes.md), [ADR-0019](../../adr/0019-no-translator-abstraction.md)

## Constants

| Constant | Value | Description |
| --- | --- | --- |
| `DOMAIN` | `'wptoolkit'` |  |

## Methods

### `__construct()`

```php
public function __construct(Identity $identity, string $languagesDir);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$identity` | [`Identity`](Identity.md) |  |
| `$languagesDir` | `string` |  |

### `bundledDirectory()`

```php
public static function bundledDirectory(): string;
```

The library's bundled `languages/` directory.

**Returns** `string`

### `load()`

```php
public function load(): bool;
```

**Returns** `bool` — Whether a translation file was loaded by this call.

---

← [Previous: CoreServices](CoreServices.md) · [Up: Foundation](index.md) · [Next: Psr11Bridge](Psr11Bridge.md) →
