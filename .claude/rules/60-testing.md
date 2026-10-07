# Rule 60 — Testing

Decision: [ADR-0010](../../docs/adr/0010-testing-strategy.md). Detail: [05](../../docs/architecture/05-quality-testing-ci.md).

## Required minimum per change

| Change                          | Required tests                                                      |
| ------------------------------- | ------------------------------------------------------------------- |
| Bug fix                         | A test that **fails before** the fix — show it failing              |
| Security control                | A regression test from the attacker's side (anonymous, wrong user)  |
| New contract                    | An abstract contract suite                                          |
| New adapter                     | Extends the contract suite + integration test against real WordPress |
| Pure logic                      | Unit test (Brain Monkey for WP functions)                           |
| Hooks, `WP_Query`, REST, `$wpdb` | Integration test (wp-phpunit)                                      |
| Anything global to WordPress    | Coexistence E2E still green                                         |
| Guard / bootstrap               | Safe-boot E2E + `php -l` on the oldest PHP                          |
| User-facing string              | i18n gate (make-pot drift) passes                                   |
| Porting a 0.x class             | Characterization tests against `legacy/` first                      |

## Rules

- Never weaken an assertion to make a test pass. Never mark a failing test skipped without an
  issue link in the skip message.
- Tests build a fresh `Application` — no shared state between tests.
- Test names say the behaviour: `test_anonymous_user_cannot_read_private_meta`.
- Run `composer verify` before claiming done; paste failures, don't summarize them away.
