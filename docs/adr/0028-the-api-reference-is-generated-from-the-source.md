# ADR-0028 — The API reference is generated from the source, with machine-readable front matter

- **Status:** Accepted
- **Date:** 2026-10-18
- **Deciders:** codad5
- **Affects:** `docs/api/` (generated), `tools/apidocs/`, `bin/generate-api-docs.php`, `composer verify`, CI

## Context

The guides explain how to do things; nothing lists what exists. The plan deferred "API reference from
docblocks" to 1.1, and 0.x's hand-maintained `API.md` (5,075 lines) is the cautionary tale: it went stale
and was retired. The library has about 150 classes whose docblocks already say *why* (a repo rule), and its
readers now include AI agents and documentation tools that want to walk the reference in order and read
facts about each page without parsing prose.

## Decision

1. **The reference is generated, never hand-written.** `composer docs` reads `src/` with Reflection
   (signatures, modifiers, defaults — always in step with the code) and the docblocks (the prose), and
   writes `docs/api/`. The files are committed, so GitHub and agents can read them without running
   anything. `composer docs:check` (part of `composer verify` and CI) fails when the files differ from the
   source, or when a file is stale — the same pattern as the translation template.
2. **What is documented:** public classes, interfaces, enums and traits of the namespaces listed in
   `tools/apidocs/config.json`; their public constants, enum cases, properties and methods. Anything tagged
   `@internal` (a class, a method, a constant) is left out: hook callbacks and wiring are not API.
   Namespaces are added to the config as their docblocks are reviewed.
3. **One page per class**, at `docs/api/{Layer}/…/{Class}.md`, plus `{Layer}/index.md` per top-level namespace
   and `docs/api/index.md`. The reading order is one chain through all pages: the layers in the order the
   config lists them (the order to learn the library), each layer's classes in the order the config names
   first and then alphabetically.
4. **Every page starts with YAML front matter**, schema `wptoolkit-api/1`, in a fixed field order, all
   strings double-quoted:

   | Field | On | Meaning |
   | --- | --- | --- |
   | `schema` | all | `"wptoolkit-api/1"`; changes only with an incompatible change to this table |
   | `title` | all | The class's short name, or the layer name |
   | `type` | all | `class`, `interface`, `enum`, `trait`, `namespace` or `index` |
   | `fqcn` | classes | Fully qualified class name |
   | `namespace` | classes, layer pages | Its namespace |
   | `layer` | all | Top-level namespace under `Codad5\WPToolkit` (empty on the index) |
   | `source` | classes | Path of the source file, relative to the repository root |
   | `since` | classes | The `@since` tag; `1.0.0` when there is none |
   | `stability` | classes | `stable`, or `deprecated` when the class carries `@deprecated` |
   | `order` | all | Position in the reading order, from 0, unique |
   | `summary` | all | The first paragraph of the docblock, on one line |
   | `related` | when any | `{ adr: [7, 13] }`: ADRs the class or its methods cite |
   | `prev`, `next`, `up` | when any | `{ title, path }`; `path` is relative to **this page**, so it is also a working Markdown link |

   The footer repeats previous / up / next as ordinary links, for people reading on GitHub.
5. **`docs/api/index.json`** lists every page in reading order with the same facts. Its paths are relative
   to `docs/api/` (not to each page), so `prev`, `next` and `up` join directly to other entries' `path`.
6. **The output is deterministic**: no dates, LF line endings, forward slashes, a fixed order. Values that
   change with every release (`Application::VERSION`) are listed in the config and printed as
   `(set at release)`, so a version bump does not make the reference stale.
7. **No dependencies.** The generator is a PHP script under `tools/` (dev-only, `export-ignore`d). Node's
   test job parses the front matter with gray-matter (a dev dependency recorded in `tech-stack.md`) to
   prove that real front-matter tooling reads it.

## Options considered

- **phpDocumentor** (what the plan named). A large dependency with its own site theme; it does not emit
  the machine-readable ordering and links agents need, and its output is not Markdown to commit.
- **Hand-written pages.** The `API.md` failure again.
- **Markdown with no front matter.** An agent would have to parse headings and links to learn what a page
  is, where it sits and what comes next.
- **JSON only.** Good for tools, useless for a person browsing GitHub.

## Consequences

- A thin docblock shows as a thin page; the reference is also a to-do list for documentation.
- A public method that should not be API must be marked `@internal`.
- A change to `src/` that alters a public signature or docblock fails CI until `composer docs` is run and
  the result committed.
- Changing the front matter fields is a schema change: bump `wptoolkit-api/1` and update this table.
