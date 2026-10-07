# ADR-0019 — No `Translator` abstraction: library strings call WordPress's i18n functions directly

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** [04-localization.md](../architecture/04-localization.md), ADR-0004's contract list, Phase 1 §1.4

## Context

The first plan listed a `Contracts\Translator` with a WordPress adapter and an array adapter for
tests. Two facts make it the wrong seam:

- `wp i18n make-pot` finds strings by parsing **literal** calls — `__('Text', 'wptoolkit')`. A
  wrapper call such as `$translator->translate('Text')` is invisible to it, so every library string
  would silently drop out of the translation template.
- In unit tests, Brain Monkey already stubs `__()`, `_n()`, `esc_html__()` and friends. A
  `Translator` would have no second real implementation and no test double the tests need — it
  fails ADR-0004's own rule for adding a contract.

## Decision

Library code translates with WordPress's functions directly, always with the literal domain
`'wptoolkit'`. Only the *loading* of the library's `.mo` files is our code
(`Foundation\LibraryTranslations`). `Translator` is removed from ADR-0004's contract list.

## Options considered

### Option A — `Translator` contract (first plan)

**Lost:** breaks string extraction; no real second implementation.

### Option B — Direct WordPress i18n calls (chosen)

**Pros:** extraction works; tests already cover it. **Cons:** library code calls a WordPress
function outside an adapter — an accepted, documented exception to ADR-0004.

## Consequences

**We accept:** `__()` and friends appear in domain code, the one WordPress dependency allowed there.

**We gain:** a complete `languages/wptoolkit.pot`, checked in CI.

**This constrains:** the PHPCS `WordPress.WP.I18n` sniff enforces literal text and the `wptoolkit`
domain on every library string.

## Revisit when

WordPress's extractor supports custom translation functions by configuration.
