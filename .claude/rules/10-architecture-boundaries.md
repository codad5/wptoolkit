# Rule 10 — Architecture boundaries

Entry point: [CLAUDE.md](../../CLAUDE.md). Design: [02-system-architecture.md](../../docs/architecture/02-system-architecture.md).

## Who may depend on whom

| Layer                                         | May depend on                                  | Must not depend on            |
| --------------------------------------------- | ---------------------------------------------- | ----------------------------- |
| `Contracts`                                   | nothing                                        | everything else               |
| `Support`                                     | `Contracts`                                    | `Adapters`, domain layers     |
| `Adapters`                                    | `Contracts`, `Support`, WordPress              | domain layers                 |
| `Http`, `Data`, `View`, `Admin`, `Assets`     | `Contracts`, `Support`, each other's public API | `Adapters` (the container injects them) |
| `Foundation`                                  | everything (it wires the app)                  | —                             |
| `bootstrap/`                                  | nothing (PHP 5.6 syntax, no namespaced code)   | everything                    |
| `src/` anything                               | —                                              | `legacy/`                     |

## Concretely

- No `get_transient`, `wp_remote_*`, `$wpdb`, `error_log`, `WP_Filesystem` outside `src/Adapters`.
- No `$container->get()` outside `Foundation` and `ServiceProvider`s — that's a service locator.
- Adding a backend = a new adapter + a factory entry. If it needs a core edit, the plan is wrong.
- A new contract needs ≥ 2 real implementations or a test double tests truly need
  ([ADR-0004](../../docs/adr/0004-ports-and-adapters-at-real-seams.md)).
