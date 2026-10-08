# Rule 70 — Security

Design: [06-security.md](../../docs/architecture/06-security.md). Decision: [ADR-0008](../../docs/adr/0008-deny-by-default-one-http-pipeline.md).

1. Every route declares `->public()`, `->loggedIn()`, `->can()` or `->authorize()`. No default.
2. Per-object checks: reading or writing a post/meta checks `read_post`/`edit_post` **and** that
   the post type is the one the feature owns.
3. Every cookie-authenticated mutation verifies a nonce (`VerifyNonce` middleware).
4. Input: validate, then sanitize, before the controller. Never trust `$_GET`/`$_POST`/`$_SERVER`
   directly — use `Request`.
5. Output: escape late, in templates, through `Escaper`. `wp_kses_post` only for intended HTML.
6. SQL: `$wpdb->prepare()` always, in repositories only. `LIKE` values via `$wpdb->esc_like()`.
7. Page sizes are capped server-side. No `-1` from user input.
8. Errors: generic, translated message to the client; detail to the log. Never echo exceptions,
   SQL or paths.
9. Files: every path resolved inside an allowed root; uploads via WordPress APIs.
10. IP addresses: `REMOTE_ADDR` unless the request came through a configured trusted proxy.
11. Secrets never logged; the logger redacts configured keys.
