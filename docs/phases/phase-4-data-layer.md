# Phase 4 — Data layer: fields, entities, repositories

**Effort:** L (~1.5–2 active days) · **Depends on:** Phases 2 and 3 · **Unblocks:** Phase 5

---

## Goal

**Replace the 2,655-line `Model` and 1,272-line `MetaBox` with small parts that each do one job: a
field system, entities, repositories with swappable storage, a query builder and a search that is
actually correct.**

This is the critical path and the biggest phase. It's where most consumer code lives.
([ADR-0009](../adr/0009-entity-and-repository-not-active-record.md))

---

## Scope

### 4.1 Field system (Factory + Strategy)

- [x] `Data\Field\Field` (immutable definition) with `sanitize()`, `validate()`, `render()` delegated
      to its type
- [x] `Data\Field\FieldFactory`: `text`, `textarea`, `number`, `email`, `url`, `tel`, `select`,
      `checkbox`, `radio`, `date`, `color`, `hidden`, `password`, `media` (alias `wp_media`), `wysiwyg`.
      `relation`, `repeater` and `group` moved to 1.x — no consumer uses them and 0.x never had them
- [x] **Third-party field types register with the factory** — no edits to core (replaces the
      `match ($field['type'])` blocks)
- [x] Every field: `<label for>`, `aria-describedby` for its description, translatable labels
      (save errors are an admin notice with `role="alert"`, not per-field)
- [x] A `sensitive` flag on field definitions (used by Settings, ADR-0021)

### 4.2 MetaBox, rebuilt on fields

- [x] `Data\MetaBox` built from fields; closes the `MetaBox::$id` read-only TODO
- [x] On every save and every Ajax read: nonce, `edit_post` capability, post type matches the box's
      screens, autosave/revision skipped
- [x] Quick edit support kept — values travel in the row's `add_inline_data` block (editors only),
      so 0.x's public `nopriv` fetch endpoint is gone rather than guarded

### 4.3 Entities and repositories (Adapter)

- [x] `Data\Entity` (field-defined attributes, dirty tracking, no persistence logic) and `#[PostType]`,
      `#[Taxonomy]`, `#[Table]`, `#[OptionStorage]` attributes; `EntityRegistrar` registers post types,
      taxonomies and the matching meta box
- [x] `Contracts\Data\Repository` (find, findMany, query, save, delete, count)
- [x] Adapters (in `src/Adapters/Repository`): `PostTypeRepository` (CPT + meta + terms),
      `OptionsRepository`, `CustomTableRepository` (`$wpdb` + `prepare()` only; `TableSchema` creates the
      table from a migration), plus `ArrayRepository` for consumers' unit tests
- [x] `RepositoryFactory` picks the adapter an entity declares
- [x] Caching through `CacheStore`, invalidated on save/delete — custom tables only; post types use
      WordPress's post and meta caches, which edits outside the repository also invalidate

### 4.3b Migrations ([ADR-0018](../adr/0018-versioned-data-migrations-expand-contract.md))

- [x] `Data\Migrations\Migration` (`id()`, `up()`, optional `down()`) and `BatchedMigration`;
      helpers `RenameMetaKey` (batched, all rows) and `RenameOption` (keeps autoload)
- [x] `Migrator`: applied ids in `{slug}_migrations`; lock with expiry (atomic `add_option`);
      idempotent runs; stops and logs on failure; admin notice (`MigrationRunner`)
- [x] Batched migrations via WP-Cron with recorded progress and resume
- [x] Runs on activation and on `admin_init` when any registered id isn't recorded
      (`Application::migrations([...])`)
- [ ] Settings upcasters (old stored shape → current shape on read) — **moved to Phase 5** with
      `Settings`, which owns the stored shape
- [x] Integration tests: rename a meta key on 1,000 posts in batches; interrupted run resumes;
      concurrent runs blocked by the lock; rollback restores

### 4.4 Query builder

- [x] `Data\Query\Query`: where / whereIn / whereTerm / status / orderBy / page, compiled to
      `WP_Query` args for post types and to prepared SQL for custom tables (one `where` for meta and
      columns — the adapter knows which is which)
- [x] Hard cap on page size (`Query::MAX_PER_PAGE` = 100); no "all rows" query exists

### 4.5 Search — fixes C1

- [x] `Data\Search\Search` with OR semantics across title, content, chosen meta keys and terms
      (ID-union of WP_Query sub-queries, each capped at 500 candidates — no raw SQL)
- [x] Respects post status and the entity's visibility policy (capabilities, not `is_admin()`); meta
      in results only via an explicit allow-list (`expose()`); sensitive fields refused
- [x] Relevance scorers are strategies (`Scorer`; built-ins `Scorers::title()`, `content()`, `fields()`)

### 4.6 Admin columns

- [x] `Admin\Columns` extracted from `Model`: declared per meta box (so per entity via
      `EntityRegistrar::entity()`'s box), placed like 0.x (`after_title`/`after_date`/`end`), sortable by
      meta value or a callback, formatted (date, number, currency, choice labels, media thumbnails),
      and quick-edit aware — the column id is the meta key the meta box's quick edit answers to

### 4.7 Security acceptance scenarios (carried over from 0.x)

- [x] `test_anonymous_user_cannot_read_metabox_data` (S2) — `MetaBoxTest`
- [x] `test_metabox_data_requires_edit_post_and_matching_post_type` (S2) — `MetaBoxTest`
- [x] `test_non_public_post_type_is_not_searchable_by_logged_out_users` (S1) — `SearchOnWordPressTest`
- [x] `test_public_search_returns_only_published_posts` (S1 — visibility never consults `is_admin()`)
- [x] `test_public_search_never_returns_or_searches_meta` (S1)
- [x] `test_search_page_size_is_capped` (S1)

### 4.8 Data compatibility ([ADR-0016](../adr/0016-1-0-reads-0x-data-unchanged.md))

- [ ] Fixture database with 0.x-written meta (single, serialized array, multiple media rows,
      custom prefix) and options (hyphenated slug)
- [ ] 1.0 `MetaBox`/`PostTypeRepository` and `Settings` read every fixture value identically, and
      writes produce the same keys and formats
- [ ] Round trip: 1.0 writes → 0.x reads (downgrade stays possible)

### 4.9 Port and delete

- [ ] Todo example on entities + repositories; port `pau-alumni-manager`'s model shapes as fixtures
- [ ] Delete `legacy/DB/Model.php` and `legacy/DB/MetaBox.php`; fill in the migration map

---

## Definition of Done

- Search "foo" across title + meta returns posts matching **either** (C1 regression test), never a
  draft or private post to a user who can't read it, and never a meta key outside the allow-list.
- A custom field type from a test plugin registers with `FieldFactory` and renders, sanitizes and
  validates with **no change to `src/`**.
- The same `Book` entity runs against `PostTypeRepository` and `CustomTableRepository` with one
  config change; the shared repository contract tests pass for both.
- An anonymous request cannot read any metabox data (S2 regression, new layer).
- `Model.php` and `MetaBox.php` are gone from `legacy/`.

## Deliberately not in this phase

Relationships beyond `relation` fields (no ORM). Schema diffing for custom tables (migrations are
hand-written). Block editor (Gutenberg) sidebar panels (1.1).

## Risks

| Risk                                                     | Mitigation                                                           |
| -------------------------------------------------------- | -------------------------------------------------------------------- |
| OR-search across meta is slow on large sites             | Allow-listed keys only; an index advice note; benchmark with 50k posts |
| Consumers relied on `Model`'s singleton accessors        | Migration guide + a thin `legacy` shim during 0.x → 1.0 if dogfooding shows the need |
