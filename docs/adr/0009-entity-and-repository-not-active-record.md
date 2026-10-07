# ADR-0009 — Entities and repositories replace the active-record `Model`

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `src/Data`, `src/Admin/Columns`, [07-migration-from-0x.md](../architecture/07-migration-from-0x.md)

## Context

0.x `Model` (2,655 lines) is a singleton that registers the post type, defines fields, does CRUD,
caches, searches, renders admin columns, handles quick edit, serves Ajax, gates front-end access and
exports data. It can only store data as a custom post type.

## Decision

- An **Entity** holds typed data and declares its fields and storage; it does not persist itself.
- A **Repository** (contract + adapters: post type, options, custom table) persists entities. The
  entity declares which adapter; `RepositoryFactory` provides it.
- Search, admin columns, access control and HTTP endpoints are separate classes in their own layers.

## Options considered

### Option A — Keep active record, split the file

**Lost:** storage stays welded to post types; static `find()` calls keep needing global state.

### Option B — A full ORM (relations, identity map, unit of work)

**Lost:** far beyond what WordPress plugins need; heavy to maintain.

### Option C — Entity + repository (chosen)

**Pros:** storage is swappable; each part is small and testable in memory. **Cons:** consumers write
`$books->find($id)` instead of `Book::find($id)` — a migration step.

## Consequences

**We accept:** a breaking change for every 0.x `Model` user, covered by the migration guide.

**We gain:** custom tables for high-volume data without changing domain code.

**This constrains:** `$wpdb` appears only in the repository adapters and migrations (CLAUDE.md §5).
The adapters live in `src/Adapters/Repository` with the other adapters (Rule 10); `Data` holds the
entity, its attributes, `Query` and the registrar, and never imports an adapter.

**Implementation notes (Phase 4):** `Entity::fields()` is static so a definition is read once per
class (`EntityDefinition`). Post columns are opt-in (`#[PostType(columns: ['title' => 'post_title'])]`):
mapping by field name would have moved 0.x meta fields called `title` or `status` (pau has one) into
`wp_posts`, breaking ADR-0016. Every adapter passes
`tests/Contract/RepositoryContract.php`; `ArrayRepository` exists so consumers can unit-test domain
code with the same semantics.

## Revisit when

Consumers repeatedly need relations beyond `relation` fields — then consider a minimal relation API.
