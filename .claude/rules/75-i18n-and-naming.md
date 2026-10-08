# Rule 75 — Localization and naming

Decision: [ADR-0006](../../docs/adr/0006-library-text-domain-and-consumer-prefixes.md). Design: [04](../../docs/architecture/04-localization.md), [03](../../docs/architecture/03-multi-version-coexistence.md).

## Strings

- Library-owned strings: literal domain `'wptoolkit'`. Never a variable domain.
- Placeholders: `sprintf()` with numbered args (`%1$s`) and a `/* translators: */` comment.
- Counts: `_n()`. Ambiguous words: `_x()`.
- Output: `esc_html__()` / `esc_attr__()` where translating and printing in one step.
- Labels a consumer passes in are already translated — never wrap them in `__()` again.
- Dates `wp_date()`; numbers `number_format_i18n()`.
- Nothing translated before `init`.

## Names in WordPress's global namespace

Always from `Foundation\Identity`, never a string literal:

`hook()` · `ajaxAction()` · `restNamespace()` · `optionKey()` · `transientKey()` · `table()` ·
`cronHook()` · `handle()` · `jsAccessor()` (data lives at `window.wptoolkit[slug]`; merge, never replace) · `nonceAction()`

Only exceptions: the coexistence ledger and the `wptoolkit/loaded` diagnostic action.
