# 01 — Principles and constraints

Every PR is reviewed against these. When two conflict, the earlier one wins.

1. **Never take the site down.** A library bug or environment mismatch degrades one plugin, visibly,
   never the whole site. ([ADR-0013](../adr/0013-plugins-boot-through-a-syntax-safe-guard.md))
2. **Secure by default.** The safe behaviour needs no code; the unsafe one needs an explicit,
   greppable opt-out (`->public()`).
3. **Coexist.** Assume another plugin on the same site bundles a different version of us.
   ([ADR-0005](../adr/0005-multiple-copies-coexist-via-scoping.md))
4. **No global state.** One `Application` per plugin; nothing static that holds data.
5. **Depend on contracts at real seams**, not on WordPress functions in domain code.
6. **Translatable and accessible by default.**
7. **Don't force a toolchain.** Composer optional, Node optional for consumers, zero runtime deps.
8. **Explicit over magic.** No `__get`/`__set` on core objects; every hook we add is listable.
9. **Small units.** ~400 lines per class, ~40 per method.
10. **Write decisions down.** ADRs for anything a future reader could question.

## Fixed constraints

| Constraint           | Value                                        | Source                                              |
| -------------------- | -------------------------------------------- | --------------------------------------------------- |
| PHP (1.x)            | ≥ 8.1                                        | [ADR-0002](../adr/0002-php-8-1-and-wordpress-6-4-floor.md) |
| PHP (guard file)     | parses on 5.6                                | [ADR-0013](../adr/0013-plugins-boot-through-a-syntax-safe-guard.md) |
| WordPress            | ≥ 6.4                                        | ADR-0002                                            |
| License              | GPL-2.0-or-later                             | [ADR-0003](../adr/0003-gpl-2-0-or-later.md)         |
| Runtime dependencies | none                                         | [ADR-0012](../adr/0012-zero-runtime-dependencies.md) |
| Install paths        | Composer package, standalone zip             | [ADR-0014](../adr/0014-composer-is-optional.md)     |
