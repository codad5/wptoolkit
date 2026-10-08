# Phase 3 — HTTP layer: one pipeline for Ajax and REST

**Effort:** M (~1 active day) · **Depends on:** Phase 2 · **Unblocks:** Phase 4's endpoints, Phase 5's admin

---

## Goal

**One controller, one middleware pipeline, two transports. Every route is denied until it says
otherwise, every error has the right HTTP status, and no internal detail reaches a client.**

0.x has two parallel systems (`Ajax`, 777 lines; `RestRoute`, 1,002 lines) that each re-implement
nonces, capabilities and rate limits — and disagree on defaults. That is how S3 happened.
([ADR-0008](../adr/0008-deny-by-default-one-http-pipeline.md))

---

## Scope

### 3.1 Request and response

- [x] `Http\Request` (readonly): method, route params, query, body, files, user, transport
- [x] `Http\Response` + `JsonResponse`, `ErrorResponse` with a stable error shape
      `{ code, message, details? }` — `details` only for users who can `manage_options` with `WP_DEBUG`
- [x] Typed exceptions → status codes: `ValidationFailed` 422, `Unauthenticated` 401, `Forbidden` 403,
      `NotFound` 404, `TooManyRequests` 429, anything else 500 with a generic, translated message

### 3.2 Router and routes

- [x] `Http\Router` + fluent `Route` builder: `get/post/put/patch/delete`, `->can()`, `->public()`,
      `->loggedIn()`, `->rateLimit()`, `->validate()`, `->exposeVia('rest', 'ajax')`
- [x] **A route without an access rule throws at registration** (in dev) and is denied (in production)
- [x] `Router::routes()` exposes every route for `routes:list` (Phase 6)

### 3.3 Middleware pipeline

- [x] `Contracts\Http\Middleware` + pipeline (chain of responsibility)
- [x] Built into the Dispatcher rather than separate middleware classes (fewer moving parts, one order):
      access rule, `VerifyNonce` (Ajax), rate limit, validation. Was: built-ins `Authenticate`, `Authorize`, `VerifyNonce` (Ajax and
      cookie-authenticated REST), `RateLimit` (Phase 2), `ValidateInput`, `SanitizeInput`
- [x] Global and per-route middleware; order is explicit and tested

### 3.4 Transports

- [x] `AjaxTransport`: registers `wp_ajax_{identity action}` (+ `nopriv_` only for `->public()` routes);
      reads `_wpnonce`; sends the real status code
- [x] `RestTransport`: `register_rest_route` under `Identity::restNamespace()`; maps rules to
      `permission_callback` and `args`
- [x] The same controller method serves both, returning the same JSON shape

### 3.5 Validation and sanitization — ports 0.x `Utils/InputValidator`

- [x] `Support\Validation\Validator` + rule objects (`Required`, `Email`, `Url`, `Min`, `Max`, `In`,
      `Regex`, `Callback`, …) — Strategy pattern; consumers add rules without editing core
- [x] `Support\Sanitization` per type, applied before the controller sees input
- [x] Error messages are translatable and keyed, so a client can map them

### 3.6 JavaScript client — replaces `assets/js/wptoolkit-ajax.js`

- [x] `resources/js/client.js`: dependency-free classic script (works on WP 6.4 without a build); versioned
      factory under the locked `window.wptoolkit`; REST + Ajax with nonces; `ToolkitError`. Was: client on `@wordpress/api-fetch`; translations via
      `wp_set_script_translations`
- [x] Tested with Node's built-in test runner (no npm dependencies) in CI. Was: `@wordpress/scripts`; ESLint + Vitest

### 3.7 Port and delete

- [x] → **Phase 5 §5.6** (delete `legacy/` as a whole, done): port `legacy/Utils/Ajax.php` and `legacy/Utils/RestRoute.php` consumers in the example plugin;
      fill in the migration map

---

### 3.8 Security acceptance scenarios (carried over from 0.x)

Each is a named test against the new layer; the parked 0.x versions on `fix/0.2.1-security` are the
reference ([track-0x-maintenance.md](track-0x-maintenance.md)).

- [x] `test_route_without_access_rule_is_denied` (S3)
- [x] `test_anonymous_user_cannot_call_a_logged_in_route` (S3)
- [x] `test_exception_message_never_reaches_the_client` (S4) — on both transports
- [x] `test_error_responses_carry_the_real_http_status` (C2) — on both transports

## Definition of Done

> **Met 2026-10-07** except the example plugin's frontend (rebuilt in Phase 6 §6.4): the four §3.8
> scenarios pass in unit tests on both transports and on WordPress's real REST server
> (`tests/Integration/RestOnWordPressTest.php`); 422 with field messages; 429 with Retry-After;
> generic 500 with request ID and logged detail.

- **The S1–S4 regression tests from Phase 0, rewritten against the new layer, pass on both
  transports.**
- A route registered with no access rule: throws in a dev environment; denies with 403 in
  production.
- A validation failure returns **422** with field-keyed, translated messages on both Ajax and REST.
- An exception thrown in a controller returns a generic 500 to an anonymous user, and the exception
  is in the log with the request ID.
- The Todo example's frontend works through the new JS client.

## Deliberately not in this phase

GraphQL. Webhook signature verification helpers (1.1). OpenAPI generation (1.1, from route metadata).

## Risks

| Risk                                                              | Mitigation                                                    |
| ----------------------------------------------------------------- | ------------------------------------------------------------- |
| REST cookie-auth nonce (`wp_rest`) vs Ajax nonce semantics differ | `VerifyNonce` is transport-aware; tested per transport         |
| A strict default breaks quick prototypes                          | `->public()` is one call, and the error says exactly that      |
