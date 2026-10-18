---
schema: "wptoolkit-api/1"
title: "Http"
type: "namespace"
namespace: "Codad5\\WPToolkit\\Http"
layer: "Http"
order: 14
summary: "One pipeline for REST, admin-ajax and public pages: the router and its fluent routes, requests and responses, and the dispatcher that enforces access, validation and rate limits."
prev: { title: "ToolkitCompatibility", path: "../Foundation/ToolkitCompatibility.md" }
next: { title: "Router", path: "Router.md" }
up: { title: "API reference", path: "../index.md" }
---

# Http

One pipeline for REST, admin-ajax and public pages: the router and its fluent routes, requests and responses, and the dispatcher that enforces access, validation and rate limits.

| Page | Kind | Description |
| --- | --- | --- |
| [Router](Router.md) | class | Collects routes and registers them with WordPress — REST and/or admin-ajax — through the same Dispatcher (ADR-0008). Replaces 0.x `Ajax` and `RestRoute`. |
| [Route](Route.md) | class | One route, built fluently (Builder). Declares who may call it — there is no default (ADR-0008) — and how it's exposed. |
| [Request](Request.md) | class | An incoming request, the same shape whether it arrived over REST or admin-ajax (immutable). |
| [Response](Response.md) | class | An outgoing response, sent as JSON by either transport (immutable). |
| [HttpError](HttpError.md) | class | An error meant for the client: it carries the HTTP status, a stable machine-readable code and a message safe to show. Anything else thrown by a controller becomes a generic 500 (ADR-0008). |
| [Dispatcher](Dispatcher.md) | class | The one request pipeline both transports use (ADR-0008), so every security rule is written once: |
| [ApiClient](Client/ApiClient.md) | class | A client for one remote API (ports 0.x `APIHelper`): base URL, authentication, JSON, retries with backoff, cached GETs and logging — over any HttpClient (ADR-0004). |
| [Auth](Client/Auth.md) | class | How ApiClient authenticates each request (Strategy). |
| [AjaxTransport](Transport/AjaxTransport.md) | class | Exposes routes through admin-ajax as `{slug}_{route}` actions. Logged-out access (`wp_ajax_nopriv_…`) is registered only for `->public()` routes; the Dispatcher still checks. |
| [RestTransport](Transport/RestTransport.md) | class | Exposes routes on the WordPress REST API under the consumer's namespace (`my-plugin/v1`). |

---

← [Previous: ToolkitCompatibility](../Foundation/ToolkitCompatibility.md) · [Up: API reference](../index.md) · [Next: Router](Router.md) →
