# Rule 20 — PHP

- `declare(strict_types=1);` in every `src/` file. GPL-2.0-or-later header.
- PHP **8.1** on `next`; **8.0** on `0.x`; **5.6 syntax** in `bootstrap/` and consumer main files.
- Types on every parameter, return and property. `mixed` only with a comment saying why.
- `readonly` for value objects; `enum` for closed sets; `final` by default — open a class for
  extension deliberately.
- Constructor injection only. No `new` of a service inside another service (value objects are fine).
- No `__get`/`__set`/`__call` on library classes.
- Class names via `::class`, never in strings (scoping — [03](../../docs/architecture/03-multi-version-coexistence.md)).
- No global functions or constants in `src/`. No static properties that hold data.
- Exceptions: our own typed hierarchy under `Codad5\WPToolkit\Exceptions`; never swallow — log or
  rethrow. `WP_Error` only at the WordPress boundary (adapters, transports).
- ~400 lines per class, ~40 per method. PHPStan level 8 clean, no new baseline entries.
- Docblocks explain *why* and document array shapes (`@param array{slug: string, …}`); don't restate
  the signature.
