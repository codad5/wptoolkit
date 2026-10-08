# ADR-0010 — Unit with Brain Monkey, integration with wp-phpunit, E2E with Playwright

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `tests/`, CI, [05-quality-testing-ci.md](../architecture/05-quality-testing-ci.md)

## Context

0.x has no tests. A WordPress library needs three kinds of confidence: logic correct in isolation,
correct behaviour inside real WordPress (hooks, `WP_Query`, the REST server, `$wpdb`), and correct
behaviour as a user sees it (admin screens, RTL, two plugins on one site).

## Decision

| Layer       | Tooling                                                     | Runs                     |
| ----------- | ----------------------------------------------------------- | ------------------------ |
| Unit        | PHPUnit + Brain Monkey (WP function mocks) + in-memory adapters | every push, PHP matrix |
| Contract    | Abstract PHPUnit suites every adapter must pass             | every push               |
| Integration | wp-phpunit + WordPress test suite + MySQL                   | every push, PHP × WP matrix |
| E2E         | `@wordpress/env` + Playwright on example and fixture plugins | PR label `e2e` + nightly; required before release |
| JS          | Vitest + ESLint                                             | every push               |

Every bug fix starts with a test that fails. Every security control has a regression test.

### Amendment (2026-10-07): integration tests boot WordPress with wp-load.php, not wp-phpunit

WordPress's own PHPUnit test library supports PHPUnit only up to 9.6; this project uses PHPUnit 10.
Rather than run two PHPUnit versions, the integration suite (`tests/Integration`,
`phpunit.integration.xml.dist`) runs inside wp-env's tests container and bootstraps a real WordPress
with `wp-load.php`: real MySQL, transients, object cache, HTTP API and WP_Filesystem. Tests isolate
themselves with unique keys and clean up after themselves instead of relying on the library's
transaction rollback. Run with `composer test:integration` (wp-env must be started).

## Options considered

### Option A — Integration tests only

**Lost:** slow feedback; logic bugs hidden behind WordPress setup.

### Option B — Mock everything (unit only)

**Lost:** mocks of WordPress drift from WordPress; S1–S3-type bugs live exactly in that gap.

### Option C — The pyramid above (chosen)

## Consequences

**We accept:** Docker for integration and E2E locally; slower CI for the matrix.

**We gain:** each class of bug is caught at the cheapest layer that can see it.

**This constrains:** [60-testing.md](../../.claude/rules/60-testing.md); coverage gates
(≥ 90% `Foundation`, `Http`, `Support`; ≥ 80% overall).

## Revisit when

CI time on a PR exceeds ~10 minutes — then shard, or move the full WP matrix to nightly.
