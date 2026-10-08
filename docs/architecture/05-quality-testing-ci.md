# 05 — Quality, testing and CI

Decisions: [ADR-0010](../adr/0010-testing-strategy.md),
[ADR-0011](../adr/0011-conventional-commits-semver-release-automation.md).

## Test layers

| Layer       | Tooling                                         | Covers                                              | Gate |
| ----------- | ----------------------------------------------- | --------------------------------------------------- | ---- |
| Unit        | PHPUnit + Brain Monkey + in-memory adapters     | Container, middleware, validation, fields, query builder | ≥ 90% on `Foundation`, `Http`, `Support` |
| Contract    | Abstract suites per contract                    | Every adapter behaves identically                    | all adapters pass |
| Integration | wp-phpunit + WP test suite + MySQL              | Repositories, transports, hooks, i18n loading        | every adapter and transport |
| E2E         | wp-env + Playwright                             | Admin flows, RTL, coexistence, safe boot             | required before release |
| JS          | Vitest + ESLint                                 | API client                                           | ≥ 80% |
| Security    | Regression tests S1–S4 + Psalm taint analysis   | Access control, injection, XSS                       | never regress |

## Static analysis

PHPStan level 8 (+ `szepeviktor/phpstan-wordpress`) on `src/`; Psalm `--taint-analysis`;
PHPCS (WordPress-Extra, PHPCompatibilityWP `8.1-` for `src/`, `5.6-` for `bootstrap/guard.php` and
example main files); Rector for upgrades; `composer normalize`; `composer audit`.

## GitHub Actions

```
.github/workflows/
  ci.yml        pull_request, push(main, 0.x, next)
    validate     composer validate · normalize --dry-run · audit
    lint         php -l (matrix) · PHPCS · ESLint · commit messages
    guard        php -l bootstrap/guard.php on the oldest PHP image available
    analyse      PHPStan · Psalm taint
    unit         PHP 8.1 → newest stable
    integration  PHP {8.1, newest} × WP {6.4, latest, nightly} + MySQL service
    i18n         make-pot, fail on drift
    coverage     upload; gate on thresholds
  e2e.yml       label `e2e` + nightly: wp-env + Playwright (coexistence, safe boot, RTL)
  release.yml   tag v*: Composer dist + standalone zip, changelog, GitHub Release, Packagist
.github/dependabot.yml · PULL_REQUEST_TEMPLATE.md · ISSUE_TEMPLATE/ · CODEOWNERS
```

Branch protection on `main` and `0.x`: required checks = validate, lint, guard, analyse, unit,
integration, i18n; one review; linear history. README badges point at real workflows only.
