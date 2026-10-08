# Phase 2 — Infrastructure adapters

**Effort:** M (~0.75 active day) · **Depends on:** Phase 1 · **Unblocks:** Phases 3 and 4

---

## Goal

**Every external thing the library touches — cache, HTTP, logging, rate limiting, the filesystem —
sits behind one of our contracts, with at least one WordPress adapter and one in-memory adapter, all
passing the same contract test suite.**

([ADR-0004](../adr/0004-ports-and-adapters-at-real-seams.md),
[ADR-0012](../adr/0012-zero-runtime-dependencies.md))

---

## Scope

### 2.0 Integration test infrastructure (moved from Phase 1 §1.7)

- [x] `composer test:integration`: real WordPress via `wp-load.php` inside wp-env (not wp-phpunit, which is PHPUnit ≤ 9 — ADR-0010 amendment)
- [x] CI `integration` job: wp-env's MySQL; PHP {8.1, 8.3} × WP {6.4, latest}

### 2.1 The contract-test pattern

- [x] An abstract PHPUnit test case per contract (`CacheStoreContractTest`, …). Every adapter's test
      extends it, so one behaviour list holds for every backend.

### 2.2 Cache — ports 0.x `Utils/Cache`

- [x] `Contracts\Cache\CacheStore` (get, set, delete, has, remember, increment/count, clear) — `many()` dropped (no caller needs it); flushGroup is `clear()` via generation counter
- [x] Adapters: `TransientStore`, `ObjectCacheStore`, `ArrayStore`, `NullStore`
- [x] `CacheFactory`: picks a driver from `config['cache']['driver']`, falls back to transients when
      no persistent object cache exists
- [x] Keys always go through `Identity::transientKey()`; group flush without `LIKE` scans where the
      backend supports it
- [x] Optional `Psr16Bridge` (only usable when the consumer installs `psr/simple-cache` themselves)

### 2.3 Logging — replaces 0.x `Utils/Debugger`

- [x] `Contracts\Log\Logger` (PSR-3-shaped levels and context interpolation)
- [x] Adapters: `ErrorLogLogger`, `QueryMonitorLogger` (when the plugin is active), `BrowserConsoleLogger`
      (buffers; prints only in `wp_footer` / `admin_footer`; only for users who can `manage_options`
      and only when `WP_DEBUG`), `NullLogger`, `ArrayLogger` (tests)
- [x] `Psr3Bridge` (optional, same rule as 2.2)

### 2.4 HTTP client — ports 0.x `Utils/APIHelper`

- [x] `Contracts\Http\HttpClient` + typed `HttpResponse` and `HttpException`
- [x] `WpHttpClient` (`wp_remote_request`), `FakeHttpClient` (queued responses, records requests)
- [x] `ApiClient` on top: base URL, auth strategies, retry with backoff on 429/5xx, timeouts, cached
      GETs via `CacheStore`, request/response logging with secrets redacted

### 2.5 Rate limiting — fixes C3

- [x] `Support\RateLimit\RateLimiter` (fixed window, clock-aligned) over `CacheStore` — no separate store contract needed. Sliding window: not built; add only if a consumer needs smoother limits
- [x] Store backed by `CacheStore`; non-persistent backends are **reported** (warning, once) rather than refused — refusing would turn a config mistake into an outage (an in-request array cache
      can't rate-limit across requests) and warns once
- [x] Identifier strategies: user ID, IP (honouring a configurable trusted-proxy list, never raw
      `X-Forwarded-For`), custom callable

### 2.6 Filesystem — ports 0.x `Utils/Filesystem` (trimmed)

- [x] `Contracts\Filesystem\Filesystem` + `WpFilesystem` (`WP_Filesystem`) + `InMemoryFilesystem`
- [x] Path-traversal guard: every path is resolved inside an allowed root

### 2.7 Clock

- [x] `Contracts\Clock` + `SystemClock` + `FrozenClock` — so cache expiry and rate windows are testable

---

## Definition of Done

> **Met 2026-10-07.** Contract suites pass for every cache and filesystem adapter (unit, plus
> WP_Filesystem and real transients/object cache in the integration matrix PHP 8.1/8.3 × WP 6.4/latest);
> rate limits hold across separate limiter instances on real WordPress; ApiClient retries 5xx/429 with
> backoff and never logs secrets; the migration map marks Cache, Debugger, APIHelper, Filesystem ported.
> The HTTP-level 429 response itself arrives with the Phase 3 router.

- Every contract has a shared contract-test suite, and **every adapter passes it**.
- A rate-limited route returns 429 on the N+1st request **across separate HTTP requests** on wp-env
  (proves C3 is fixed) — and with only an array cache configured, the app warns instead of silently
  not limiting.
- `ApiClient` retries a 503 then succeeds against `FakeHttpClient`; a secret header never appears in
  the log output.
- 0.x `Cache`, `Debugger`, `APIHelper`, `Filesystem` have equivalents in `src/`; their entries in
  [07-migration-from-0x.md](../architecture/07-migration-from-0x.md) are filled in.

## Deliberately not in this phase

Redis/Memcached-specific adapters (the object cache adapter covers them through WordPress). A Guzzle
adapter (consumers can write one against the contract).

## Risks

| Risk                                                    | Mitigation                                          |
| ------------------------------------------------------- | --------------------------------------------------- |
| Our contracts drift from PSR shapes and bridges get awkward | Keep method names and semantics PSR-identical; bridges are thin |
