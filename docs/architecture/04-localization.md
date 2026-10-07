# 04 — Localization

Decision: [ADR-0006](../adr/0006-library-text-domain-and-consumer-prefixes.md).

| Area              | How                                                                                           |
| ----------------- | --------------------------------------------------------------------------------------------- |
| Library strings   | Literal text domain `wptoolkit`; `__()`, `_x()`, `_n()`, `esc_html__()`; `/* translators: */` on every placeholder |
| Consumer strings  | Translated by the consumer with their own domain before they reach us; we never re-translate |
| Loading           | `load_textdomain('wptoolkit', …)` on `init`, from the package's own `languages/`; path overridable via `Identity::hook('i18n/path')` |
| Never before `init` | Avoids WordPress 6.7's just-in-time loading notice (0.x bug C5)                             |
| JavaScript        | `@wordpress/i18n` in toolkit JS; `wp_set_script_translations()` per handle; JSON via `wp i18n make-json` |
| Plurals & context | `_n()` for counts, `_x()` where a word is ambiguous                                          |
| Dates and numbers | `wp_date()`, `number_format_i18n()`, `size_format()` — never `date()` / `number_format()` in output |
| RTL               | Logical CSS properties (`margin-inline-start`); generated `-rtl.css` registered with `wp_style_add_data($h, 'rtl', 'replace')` |
| Error messages    | Keyed (`validation.required`) and translated at the edge, so clients can map keys            |
| Abstraction       | `Contracts\Translator` with `WpTranslator`; `ArrayTranslator` in tests                        |
| Accessibility     | `<label for>`, `aria-describedby` for field errors, notices with `role="status"`/`"alert"`  |

## CI gates

- PHPCS `WordPress.WP.I18n`: missing or wrong text domain fails the build.
- `composer i18n` regenerates `languages/wptoolkit.pot`; the build fails if it differs from the
  committed file.
- E2E runs in `en_US` and `ar` (RTL) from Phase 5.
