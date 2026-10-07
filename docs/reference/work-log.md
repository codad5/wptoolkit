# Work log

One dated entry per working session: what landed, what was learned, what's next.

## 2026-10-07

- **Landed:** sync check (`dev` = `origin/dev` @ `11db13b`; local `main` fast-forwarded to
  `9e4473c`); 0.x review (50/100); branch `next`; agent and docs scaffolding: `CLAUDE.md`, rules,
  architecture 00–08, ADRs 0001–0014, phases 0–7 + 0.x track.
- **Learned:** the guard must live in an old-syntax file that returns a closure, because PHP parses
  a whole file before running it; `ParseError` from an *included* file is catchable on PHP 7+.
  Composer must be optional (traditional PHP developers), so scoping needs a zero-tool path.
- **Next:** Phase 0.1 (maintainer: merge PR #4, tag, cut `0.x`), then the 0.2.1 security hotfix.
