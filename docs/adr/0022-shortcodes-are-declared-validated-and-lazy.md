# ADR-0022 — Shortcodes are declared, validated, access-checked and load their assets lazily

- **Status:** Accepted
- **Date:** 2026-10-11
- **Deciders:** codad5
- **Affects:** `Frontend\Shortcodes` (new), `Assets\Asset` / `AssetManager` (on-demand assets), `Foundation\Identity`, `make:shortcode`

## Context

1.0 has no shortcode support, though shortcodes are one of the commonest ways a plugin prints
output. Hand-written shortcodes repeat the same faults:

- attributes pass through `shortcode_atts()`, which supplies defaults but validates and types nothing;
- the handler `echo`es instead of returning, scrambling output order;
- assets load on every page, or are enqueued too late;
- the tag is a bare, unprefixed string that another plugin can claim;
- a REST or Ajax route that rebuilds `[tag attr=…]` from request parameters and runs it
  (a pattern found in a real plugin) is a shortcode-injection hole, because there was no safe
  alternative to calling the handler.

## Decision

1. A shortcode is declared on `Shortcodes::add($name, $handler)` and returns a fluent `Shortcode`.
2. **Tags are prefixed**: the default tag is `{underscored_slug}_{name}` from `Identity::shortcodeTag()`.
   A plugin that already ships a tag (it is stored in users' content, ADR-0016) passes it explicitly
   with `tag:`; a tag already registered by anyone else is refused at registration.
3. **Attributes are declared** with the same definition shape as `Route::args()` (`type`, `rules`,
   `default`, `label`) and run through the same `Validator`. Undeclared attributes never reach the
   handler. An attribute that fails validation falls back to its default (or `null`) and is logged
   by name — never by value — because a visitor cannot fix a post's content.
4. **Access is explicit**: `->public()`, `->loggedIn()` or `->can($capability)`. A shortcode with no
   access rule is refused (prints nothing, logged); in development it throws at registration, as a
   route does. There is no implicit "public".
5. **Handlers return**; they never print. With `->view('front/widget')` the handler returns the view's
   data and the toolkit renders it through the escaping renderer; without a view the handler returns
   an HTML string it is responsible for escaping.
6. **Assets load lazily**: `->assets('widget', 'widget-style')` enqueues named assets only when the
   shortcode actually renders (and only if access passed). Assets used this way are declared
   `->onDemand()` so the normal enqueue hooks skip them.
7. **A failing handler never takes the page down**: the exception is logged; the shortcode prints
   nothing. In development it is rethrown.
8. There is no helper that executes a shortcode from a string a client sent. Code that needs the
   output of a shortcode calls `Shortcodes::render($name, $attributes)`, which validates exactly as
   a page render does.

## Options considered

- **Do nothing / document `add_shortcode`.** Leaves every consumer to rediscover the faults above.
- **Shortcodes as routes** (dispatch through `Dispatcher`). Reuses validation and access, but a
  shortcode has no request, no transport and no response status; the fit is forced and the error
  semantics (a broken shortcode must not break the post) differ. We reuse the pieces instead
  (`Validator`, the access vocabulary), not the pipeline.
- **A Gutenberg block as the primary API.** Wanted later; a block can wrap a shortcode. Out of scope.

## Consequences

- Additive: ships in 1.1, no change to existing APIs apart from the new `Asset::onDemand()`.
- Enclosing content is passed to the handler as a raw string (authors of the post wrote it);
  handlers must escape it like any other text.
- Nested-shortcode helpers and a visual builder are deliberately not provided.
