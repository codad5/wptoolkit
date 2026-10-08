# Routes and security

One router serves REST, admin-ajax and front-end pages through one pipeline:

```
access rule → nonce (Ajax writes) → rate limit → validation & sanitizing → middleware → your controller
```

Every security check is written once and runs for every transport
([ADR-0008](../adr/0008-deny-by-default-one-http-pipeline.md)).

## Declaring routes

In a service provider's `boot()`:

```php
public function boot(Router $router): void
{
    $router->get('books', [BookController::class, 'index'])->public();

    $router->post('books', [BookController::class, 'store'])
        ->can('edit_posts')
        ->rateLimit(10, perSeconds: 60, by: 'user')
        ->args([
            'title' => ['rules' => 'required|max:200'],
            'pages' => ['rules' => 'integer|min:1', 'type' => 'int'],
        ]);

    $router->delete('books/{id}', [BookController::class, 'destroy'])
        ->authorize(fn (Request $r): bool => current_user_can('delete_post', (int) $r->input('id')))
        ->args(['id' => ['rules' => 'required|integer', 'type' => 'int']]);
}
```

- REST: `/wp-json/{slug}/v1/books`. Ajax: action `{slug}_books`. Add `->exposeVia('rest', 'ajax')`
  for both, or `->exposeVia('ajax')` for Ajax only. `->version('v2')` changes the REST namespace.
- Routes on one path with different methods share a name (`books`); different paths must not.
  Name a route explicitly with `->name('…')`.

## Access rules — required

| Rule | Who |
| --- | --- |
| `->public()` | Anyone, logged out included. The only way to open a route. |
| `->loggedIn()` | Any logged-in user. |
| `->can('edit_posts')` | Logged in with that capability (meta capabilities work with `authorize()`). |
| `->authorize(fn (Request $r) => …)` | Your own check, e.g. "may edit *this* post". |

A route without a rule throws at registration in development and answers 403 in production. Check
with `wp {slug} routes:list`.

For a post or its meta, check **both** the per-object capability (`edit_post`, `read_post`) and that
the post is of the type your feature owns.

## Input

Only declared `args` reach your controller, validated then sanitized:

```php
public function store(Request $request): Response
{
    $title = $request->input('title');      // validated, sanitized string
    …
    return Response::created(['id' => $id]);
}
```

**Rules** (combine with `|`): `required`, `email`, `url`, `integer`, `numeric`, `boolean`, `min:n`,
`max:n`, `in:a,b,c`, `regex:/…/`. **Types** (the sanitizer): `text` (default), `textarea`, `email`,
`url`, `int`, `float`, `bool`, `key`, `slug`, `html`, `array_text`, `raw`. An unknown type is an error
when the route is declared.

Invalid input answers 422 with `{code, message, details: {field: [messages]}}`.

## Responses and errors

Return an array (JSON 200), a `Response` (`Response::json()`, `::created()`, `::noContent()`), or throw
an `HttpError` (`::notFound()`, `::forbidden()`, `::badRequest()`, …). Any other exception is logged
with a request id and the client gets a generic 500 — never a message, trace or SQL. Ajax answers
with real status codes too, not `200 {success: false}`.

## Nonces

REST: WordPress checks `X-WP-Nonce` for cookie-authenticated requests. Ajax: every non-public
mutation must carry the route's nonce — the JS client sends it for you.

## Rate limits

`->rateLimit($max, perSeconds: $window, by: 'ip'|'user')`. Over the limit: 429 with `Retry-After`.
Client IPs come from `REMOTE_ADDR` unless you configure `'trusted_proxies'`.

## The JS client

```php
public function boot(AssetManager $assets, Router $router): void
{
    $assets->client($router);                                    // front end by default
    $assets->script('app', 'build/app.js')->dependsOn($assets->handle('wptoolkit-client'));
}
```

```js
const { api } = window.wptoolkit['my-books'];

try {
    const book = await api.call('books', { title: 'Dune' }, { method: 'POST' });
} catch (error) {
    // error.name === 'ToolkitError'; error.status, error.code, error.details
}
```

## Front-end pages

Pretty URLs that render a view inside the theme run through the same pipeline:

```php
$public->page('library/books/{id}', __('Book', 'my-books'), 'front/book', fn (Request $r) => [
    'book' => $books->find((int) $r->input('id')) ?? throw HttpError::notFound(),
])->public()->args(['id' => ['rules' => 'required|integer', 'type' => 'int']]);
```

A 401 sends the visitor to log in and back; a 404 shows the theme's 404 page.
