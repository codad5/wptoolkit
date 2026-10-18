---
schema: "wptoolkit-api/1"
title: "API reference"
type: "index"
layer: ""
order: 0
summary: "Every public class of WPToolkit, in the order to read them."
next: { title: "Foundation", path: "Foundation/index.md" }
---

# API reference

Generated from the source by `composer docs`; do not edit these files. Each page lists a class's public API with the reasoning from its docblocks. Pages carry machine-readable front matter (schema `wptoolkit-api/1`, [ADR-0028](../adr/0028-the-api-reference-is-generated-from-the-source.md)) and `index.json` lists them all in reading order.

| Page | Kind | Description |
| --- | --- | --- |
| [Foundation](Foundation/index.md) | namespace | The kernel: one Application per plugin, its container and providers, the hooks it adds, and the prefixed names (Identity) everything else is built from. |
| [Http](Http/index.md) | namespace | One pipeline for REST, admin-ajax and public pages: the router and its fluent routes, requests and responses, and the dispatcher that enforces access, validation and rate limits. |

---

[Next: Foundation](Foundation/index.md) →
