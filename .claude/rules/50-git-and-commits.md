# Rule 50 — Git and commits

Decision: [ADR-0011](../../docs/adr/0011-conventional-commits-semver-release-automation.md). Template: [.gitmessage](../../.gitmessage).

- **Conventional Commits.** `type(scope): subject` — imperative, lowercase, no period, ≤ 72 chars.
- **Scopes** are module names: `bootstrap foundation http data view admin assets i18n cache log
  cli coexistence legacy docs adr ci deps`.
- One logical change per commit. If the subject needs "and", split it.
- Breaking change → `!` after the scope **and** a `BREAKING CHANGE:` footer saying what to do.
- Tests ship in the same commit as the code.
- Branches: `next` for 1.0 work; `0.x` for maintenance fixes; short-lived `feat/…`, `fix/…` off
  them. Never commit directly to `main`.
- Don't push, tag, merge PRs or publish releases unless the maintainer asked in this session.
