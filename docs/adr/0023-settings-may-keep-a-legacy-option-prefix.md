# ADR-0023 — Settings may keep an option prefix that already exists in users' data

- **Status:** Accepted
- **Date:** 2026-10-11
- **Deciders:** codad5
- **Affects:** `Admin\Settings\Settings`, `SettingsForm`

## Context

Rule 3 says every name WPToolkit puts into WordPress's global namespace is prefixed by the consumer's
slug, and `Settings` builds option names as `{slug}_{name}` through `Identity::optionKey()`.

0.x `Settings` took an optional third argument, `option_prefix`, and some plugins used it; others are
adopting WPToolkit with options they saved before using it at all. In both cases the names in
`wp_options` do not start with the slug. 1.0 dropped the argument, so those plugins could only move by
renaming every user's options with a migration. That contradicts ADR-0016 (1.0 reads stored data
unchanged) and makes rolling back to the previous plugin version lose the settings.

## Decision

`Settings` accepts an optional `optionPrefix`. When given, a setting is stored in
`{optionPrefix}{sanitize_key(name)}` — exactly 0.x's rule — for reads, writes, deletes, the Settings API
registration done by `SettingsForm`, and the logger's redaction list. When omitted, nothing changes.

The prefix must be non-empty and contain only letters, digits, `-` and `_`; a resulting name longer than
`wp_options.option_name` allows is refused.

This is a narrow, deliberate exception to rule 3: the names are not new, they are already the plugin's,
and they are in users' databases. It applies to settings only. `SettingsForm`'s own option group and
every other name still come from `Identity`.

## Options considered

- **Rename with `RenameOption` migrations.** Works with no API change, but moves user data, breaks
  rollback, and has to be written once per setting by every such plugin.
- **A custom prefix on `Identity`.** Would change hooks, handles, REST namespaces and tables too — far
  more than the stored names that need preserving.
- **Per-field option names.** More flexible than anything 0.x offered; no known plugin needs it.

## Consequences

- Plugins that used `option_prefix`, or pre-toolkit option names with one common prefix, upgrade with
  no data migration.
- A plugin that picks a careless prefix can collide with another plugin's options; the guide says to
  use it only for names that already exist.
