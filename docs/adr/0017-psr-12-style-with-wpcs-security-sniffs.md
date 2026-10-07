# ADR-0017 — PSR-12 style, plus WPCS's security, database and i18n sniffs

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `phpcs.xml.dist`, every file in `src/`, [.claude/rules/20-php.md](../../.claude/rules/20-php.md)

## Context

0.x mixes conventions: `Model` uses `snake_case` methods, `Ajax` and `RestRoute` use `camelCase`.
WordPress Coding Standards (WPCS) mandate snake_case, tabs, Yoda conditions and `class-*.php` file
names — which conflict with PSR-4 autoloading and with how most PHP developers (and AI coding
agents) write modern PHP. But WPCS also contains the sniffs that catch real WordPress bugs:
missing escaping, unverified nonces, unsanitized input, unprepared SQL, wrong text domains.

## Decision

- **Style:** PSR-12; PSR-4 file names; **camelCase** methods and properties; `StudlyCaps` classes.
- **Safety:** WPCS `WordPress.Security`, `WordPress.DB.PreparedSQL`,
  `WordPress.DB.PreparedSQLPlaceholders`, `WordPress.WP.I18n` (text domain `wptoolkit`) — errors,
  not warnings.
- **Compatibility:** PHPCompatibilityWP, `testVersion 8.1-`.

## Options considered

### Option A — Full WPCS

**Pros:** looks like WordPress core. **Cons:** fights PSR-4; unfamiliar to most PHP developers and
agents; formatting churn without safety value. **Lost.**

### Option B — PSR-12 only

**Lost:** throws away the sniffs that would have caught S1–S4-class bugs.

### Option C — PSR-12 + WPCS safety sniffs (chosen)

## Consequences

**We accept:** the library doesn't look like WordPress core code; 0.x's snake_case public methods
change name in 1.0 (covered by the migration guide; persisted data unaffected — ADR-0016).

**We gain:** idiomatic modern PHP with WordPress-specific safety checks enforced in CI.

**This constrains:** `phpcs.xml.dist`; [20-php.md](../../.claude/rules/20-php.md).

## Revisit when

WPCS ships a PSR-compatible profile.
