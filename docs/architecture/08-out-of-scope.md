# 08 — Out of scope for 1.0

Written down so nobody adds them quietly. Each can be proposed for 1.1+ with an ADR.

- A full ORM (relations, identity map, unit of work) — [ADR-0009](../adr/0009-entity-and-repository-not-active-record.md)
- Block editor (Gutenberg) sidebar panels and a React admin framework
- Twig / Blade adapters in core (separate packages)
- GraphQL; OpenAPI generation (1.1, from route metadata)
- Webhook signature helpers (1.1)
- Redis/Memcached-specific adapters (the object-cache adapter covers them)
- A docs website on its own domain (GitHub Pages from `docs/` for 1.0)
- Highest-version-wins loading — rejected in [ADR-0005](../adr/0005-multiple-copies-coexist-via-scoping.md)
- Any runtime Composer dependency — [ADR-0012](../adr/0012-zero-runtime-dependencies.md)
