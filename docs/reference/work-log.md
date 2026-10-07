# Work log

One dated entry per working session: what landed, what was learned, what's next.

## 2026-10-07

- **Landed:** sync check (`dev` = `origin/dev` @ `11db13b`; local `main` fast-forwarded to
  `9e4473c`); 0.x review (50/100); branch `next`; agent and docs scaffolding: `CLAUDE.md`, rules,
  architecture 00–08, ADRs 0001–0014, phases 0–7 + 0.x track.
- **Learned:** the guard must live in an old-syntax file that returns a closure, because PHP parses
  a whole file before running it; `ParseError` from an *included* file is catchable on PHP 7+.
  Composer must be optional (traditional PHP developers), so scoping needs a zero-tool path.
- **Changed course:** merging PR #4 was blocked by the agent's safety check (merge without review).
  Started the 0.2.1 hotfix, then the maintainer froze 0.x: real exploitability on the three known
  sites is low (S1 needs a printed nonce none of them print; S2 needs a logged-in user; S3 already
  mitigated in pau). Parked the work on `fix/0.2.1-security` — **tests never run** (composer install
  hung). Found while doing it: admin-ajax runs with `is_admin()` true, so unrestricted `WP_Query`
  searches there include drafts.
- **Decided:** ADR-0016 — 1.0 keeps 0.x's stored data formats; security scenarios become named
  acceptance tests in Phases 3–4; migration guide built from the real consumers.
- **Next:** maintainer runs `composer install` on `next`; Phase 0 tooling + CI; Phase 1.
