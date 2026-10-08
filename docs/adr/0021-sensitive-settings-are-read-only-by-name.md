# ADR-0021 — Sensitive settings are readable only by name, and never leave the server by default

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `Admin\Settings` (Phase 5), the field system (Phase 4), logging, REST/Ajax serialization

## Context

A real incident in `member-directory` (since fixed there): a public REST route returned `$settings->getAll()`, which included `api_key` — the
Next.js admin API key. Nothing about the call looked dangerous. The bug was that "everything"
silently included secrets.

The maintainer proposed marking a settings field as sensitive so it can only be read by asking for
it by name, and is left out whenever all settings are fetched.

## Decision

A settings field may declare `'sensitive' => true` (API keys, tokens, passwords, webhook secrets).
For such a field:

1. **Readable only by name**: `$settings->get('api_key')` returns it. `all()`, `toArray()`,
   `jsonSerialize()`, exports and REST/Ajax responses built from settings **omit** it. There is no
   "all including secrets" shortcut; code that needs a secret names it.
2. **Never echoed back into HTML**: the admin form renders a password input with an empty value and
   a "saved — leave blank to keep" hint; submitting it empty keeps the stored value; an explicit
   "clear" control removes it.
3. **Never logged**: the settings layer registers its sensitive keys with the logger's redaction
   list, in addition to the key-name heuristics (ADR for logging, Phase 2.3).
4. **Never localized to JavaScript**: `window.wptoolkit[slug]` can't carry a sensitive field; trying
   is an exception in development.
5. **Optional encryption at rest** (`'encrypt' => true`): stored with libsodium using a key derived
   from WordPress's salts, so a database dump alone doesn't reveal it. Off by default — rotating
   salts makes the value unreadable, which the admin must understand (documented, and the field
   shows "re-enter after salt rotation" when it can't decrypt).
6. **Storage is unchanged** (ADR-0016): a sensitive, unencrypted field keeps its 0.x option key and
   format, so marking an existing field sensitive needs no migration.

## Options considered

### Option A — Status quo: all() returns everything

**Lost:** it caused a production leak already.

### Option B — Mask sensitive values in all() (`"••••"`)

**Pros:** the key is still listed. **Cons:** callers may still render or forward the mask as if it
were data; an omission is unambiguous. **Lost.**

### Option C — Omit from every bulk read, readable only by name (chosen)

**Pros:** the dangerous call becomes the safe one; a secret only moves when code names it, which
reviews can grep for. **Cons:** code that genuinely needs several secrets names each — acceptable.

## Consequences

**We accept:** `all()` is no longer "all"; the method's docblock and the guide say so plainly.

**We gain:** the member-directory-style leak can't happen through settings again.

**This constrains:** Phase 4's field system carries a `sensitive` flag; Phase 5's Settings, asset
localization and serialization honour it; a regression test reproduces the member-directory route and asserts
the key is absent.

## Revisit when

A consumer needs a secrets store outside the options table (e.g. environment variables or a
vault) — then add a `SecretSource` contract with the options table as one adapter.
