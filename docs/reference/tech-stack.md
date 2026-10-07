# Tech stack

Pinned in Phase 0 (0.4). Exact versions are filled in when each tool is added — never guessed.
Every dev dependency has a one-line justification. **Runtime dependencies: none**
([ADR-0012](../adr/0012-zero-runtime-dependencies.md)).

## Runtime

| What      | Version                        | Why                                                  |
| --------- | ------------------------------ | ---------------------------------------------------- |
| PHP       | ≥ 8.1 (1.x) · ≥ 8.0 (0.x)      | [ADR-0002](../adr/0002-php-8-1-and-wordpress-6-4-floor.md) |
| WordPress | ≥ 6.4                          | ADR-0002                                             |

## Dev (PHP)

| Package                                  | Version | Why                                         |
| ---------------------------------------- | ------- | ------------------------------------------- |
| `phpunit/phpunit`                        | _tbd_   | Test runner                                 |
| `brain/monkey`                           | _tbd_   | Mock WordPress functions in unit tests      |
| `wp-phpunit/wp-phpunit`                  | _tbd_   | WordPress test suite for integration tests  |
| `yoast/phpunit-polyfills`                | _tbd_   | Required by the WordPress test suite        |
| `phpstan/phpstan`                        | _tbd_   | Static analysis, level 8                    |
| `szepeviktor/phpstan-wordpress`          | _tbd_   | WordPress stubs and rules for PHPStan       |
| `vimeo/psalm`                            | _tbd_   | Taint analysis only                         |
| `squizlabs/php_codesniffer`              | _tbd_   | Style and sniffs                            |
| `wp-coding-standards/wpcs`               | _tbd_   | WordPress standards incl. escaping and i18n |
| `phpcompatibility/phpcompatibility-wp`   | _tbd_   | Enforces the PHP floors                     |
| `rector/rector`                          | _tbd_   | Automated upgrades; migration rules         |
| `ergebnis/composer-normalize`            | _tbd_   | Consistent `composer.json`                  |

## Dev (JS / tooling)

| Package                 | Version | Why                                     |
| ----------------------- | ------- | --------------------------------------- |
| `@wordpress/env`        | _tbd_   | Local and CI WordPress in Docker         |
| `@wordpress/scripts`    | _tbd_   | Build toolkit JS, asset manifests        |
| `@playwright/test`      | _tbd_   | E2E                                      |
| `vitest`                | _tbd_   | JS unit tests                            |
| WP-CLI `i18n` command   | _tbd_   | `make-pot`, `make-json`                  |
| Strauss                 | _tbd_   | Scoping for Composer consumers (documented, used in fixtures) |
