# 06 — Security

## Threat model (short)

| Actor                       | Wants                                       | Our control                                         |
| --------------------------- | ------------------------------------------- | --------------------------------------------------- |
| Anonymous visitor           | Read private data via Ajax/REST             | Deny by default; capability + ownership checks ([ADR-0008](../adr/0008-deny-by-default-one-http-pipeline.md)) |
| Logged-in low-privilege user | Read/modify others' posts or meta (IDOR)   | `edit_post`/`read_post` per object, post-type match |
| Attacker via forged request | CSRF on state-changing actions              | Nonces on every cookie-authenticated mutation       |
| Attacker via input          | SQL injection, XSS, path traversal          | `$wpdb->prepare` only in repositories; late escaping via `Escaper`; filesystem root guard |
| Abusive client              | Exhaust the site (unbounded queries, spam)  | Page-size caps; persistent rate limits              |
| Curious client              | Learn internals from errors                 | Generic messages; details only in logs              |
| Another plugin              | Collide with or read our keys               | Consumer-prefixed globals ([ADR-0005](../adr/0005-multiple-copies-coexist-via-scoping.md)) |
| Environment                 | Wrong PHP/WP takes the site down            | The guard ([ADR-0013](../adr/0013-plugins-boot-through-a-syntax-safe-guard.md)) |

## 0.x findings and where they're closed

| ID | Finding                                                         | 0.x fix   | 1.0 by design |
| -- | --------------------------------------------------------------- | --------- | ------------- |
| S1 | `Model` search is `nopriv`, no capability check, client-chosen meta, unbounded limit | v0.2.1 | Phase 3 + 4 |
| S2 | `MetaBox::handle_ajax` returns any post's meta to anonymous users | v0.2.1  | Phase 4       |
| S3 | REST routes default to `__return_true`                          | v0.2.1    | Phase 3       |
| S4 | Exception messages returned to clients                          | v0.2.1    | Phase 3       |

## Pre-release checklist (Phase 7)

- [x] Every route has an explicit access rule; `routes:list` shows none open by accident — refused at
      registration in development, 403 in production (`DispatcherTest::test_route_without_access_rule_is_denied`),
      public pages included
- [x] Every mutation verifies a nonce (cookie auth) or uses application passwords / OAuth (REST): the
      Dispatcher for Ajax, WordPress core for REST, `MetaBox` saves, the Settings API, notice dismissal
- [x] No `$wpdb` outside repositories; every query prepared — the one exception is `TableSchema::drop()`'s
      `DROP TABLE`, DDL on a name built from the plugin's identity (DDL can't be prepared)
- [x] Every template output goes through `Escaper`; PHPCS escaping sniffs clean (`TemplateEscapingSniffTest`)
- [ ] Psalm taint analysis clean — **deferred to 1.1**: Psalm is not in the toolchain yet (a new dev
      dependency plus WordPress stubs). 1.0 relies on PHPStan level 8, the WPCS security sniffs and the
      attacker-side regression tests
- [x] No secrets in logs (redaction tested: `LoggingTest`, `SettingsTest`)
- [x] S1–S4 regression tests green (`SearchOnWordPressTest`, `MetaBoxTest`, `DispatcherTest`)
