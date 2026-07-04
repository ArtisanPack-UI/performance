# Security Audit — v1.0.0

This document records the pre-release security audit performed against
`artisanpack-ui/performance` before the v1.0.0 release. The scope covers
input validation, output escaping, authentication/authorization, file
security, cache security, configuration security, and general code
hygiene.

- **Audit date:** 2026-07-03
- **Scope:** every file under `src/`, `routes/`, `resources/`, `config/`
- **Method:** manual code review + targeted greps for
  known-dangerous PHP functions, unescaped Blade output, and
  file-path handling
- **Result:** no critical issues found; one moderate finding fixed;
  three defensive recommendations recorded for post-release follow-up

## Input Validation

| Area | Status | Notes |
| ---- | ------ | ----- |
| RUM metrics endpoint (`MetricsApiController::store`) | Pass | All fields validated, string lengths capped, control characters rejected via a dedicated closure, metric name checked against an allow-list before persistence. |
| Embed provider IDs | Pass | `EmbedOptimizer::isValidId()` applies strict per-provider regexes before any HTML is generated: `^[A-Za-z0-9_-]{6,20}$` (YouTube), `^[0-9]{6,15}$` (Vimeo), `^[0-9]{6,25}$` (Twitter). Anything else throws `InvalidArgumentException`. |
| Resource-hint URLs | Pass | `ResourceHint` accepts caller-supplied hrefs but escapes every value at render time (`htmlspecialchars( … , ENT_QUOTES \| ENT_SUBSTITUTE \| ENT_HTML5 )`). |
| Speculation rules URLs | Pass | Serialized via `json_encode` with `JSON_HEX_TAG \| JSON_HEX_AMP \| JSON_HEX_APOS \| JSON_HEX_QUOT`, which safely emits inside `<script type="speculationrules">`. |
| SQL injection | Pass | The package uses Eloquent and the query builder throughout; there are no raw string concatenations into SQL. `CachingEloquentBuilder` extends `Illuminate\Database\Eloquent\Builder` and inherits its bindings. |
| File upload | N/A | The package does not receive file uploads directly. Media integration hooks into `artisanpack-ui/media-library`, which owns validation of uploaded files. |

## Output Escaping

Blade emits `{!! … !!}` in five templates. Each was traced to a
package-controlled string source:

| Template | Source | Verdict |
| -------- | ------ | ------- |
| `components/resource-hints.blade.php` | `ResourceHint::toLinkElement()` (HTML-escapes every attribute) | Safe |
| `components/speculative-rules.blade.php` | `SpeculativeRulesGenerator::generate()` (JSON with hex flags) | Safe |
| `components/critical-css.blade.php` | `CriticalCssExtractor` output (package-owned tokenizer/serializer) | Safe |
| `components/perf-script.blade.php` | `ScriptManager::renderOne()` — escapes attribute values via `AbstractScriptStrategy::escape()` | Safe |
| `components/perf-embed.blade.php` | `EmbedOptimizer::embedHtml()` — Twitter ID pre-validated by numeric regex before `sprintf` | Safe |

Attribute-value interpolation (`{{ … }}`) is used everywhere else and
inherits Blade's default `htmlspecialchars` behavior.

JavaScript surface (`resources/js/*.js`) writes to
`container.innerHTML` in exactly one place
(`speculative-rules.js:206`). The payload is `atob()`-decoded from
`data-embed-html`, which the server sets to base64 of the
`embed_html` string produced by `EmbedOptimizer::embedHtml()`. That
source is bounded to Twitter's numeric-only IDs, so the `innerHTML`
sink cannot receive attacker-controlled markup.

## Authentication & Authorization

- **API endpoint (`POST {api_prefix}/metrics`)** — Registered under
  the `artisanpack.performance.routes.api_middleware` group
  (defaults to `['api']`) plus a rate limiter derived from
  `api_throttle` (defaults to `60,1`). Applications can extend the
  stack to add auth (`['api', 'auth:sanctum']`) without editing the
  package.
- **Dashboard route** — `config('artisanpack.performance.dashboard')`
  exposes `middleware => ['web', 'auth']` and a `gate` key
  (`view-performance-dashboard`) as sensible defaults. Route
  registration itself lives in the host application (documented in
  the README) so applications can pick their own gate name and
  policy binding.
- **Livewire components** — See the "Recommendations" section
  below for a defense-in-depth item covering destructive actions
  on `CacheManager`.

## File Security

The package writes to disk in three places:

1. `Services/Image/FormatConverter::save*()` — resolves the destination
   directory via `dirname($path)` on a caller-supplied path. The path
   originates from an Eloquent attribute or is composed by the
   package itself; there is no user-supplied path segment.
2. `Services/ImageService` — same pattern.
3. `Console/Commands/SuggestIndexesCommand` — anchors output at
   `base_path('database/migrations')` and applies a helper
   (`anchoredRealpath`) that walks up until it finds an existing
   ancestor, then joins the remainder. Path traversal via `..` is
   rejected because the anchor must exist and any join outside
   `base_path()` is discarded.
4. `Jobs/OptimizeMediaJob::withinStorageRoot()` — guards
   destination writes by checking
   `str_starts_with( $absolutePath, $storageRoot . DIRECTORY_SEPARATOR )`
   before touching the file system.

No `include`, `require`, or `require_once` calls with dynamic paths were
found.

## Cache Security

- **Cache-key namespacing** — `CachingEloquentBuilder::KEY_PREFIX`
  (`perf:query:`) and per-feature prefixes on the other cache managers
  keep the package's entries in their own key space, preventing
  cross-tenant collisions in shared cache backends.
- **No sensitive values cached** — Cached bodies come from three
  sources: rendered page HTML (`PageCacheManager`), rendered
  fragment HTML (`FragmentCache`), and Eloquent result sets
  (`CachingEloquentBuilder`). None of the code paths write API
  tokens, secrets, or authentication material to the cache.
- **Signed query payloads** — `CachingEloquentBuilder::withQueryCache`
  now HMAC-signs serialized payloads with `config('app.key')` and
  verifies the signature on read. Legacy or tampered entries fail
  verification and fall through to a fresh compute + backfill; see
  the "Findings" section below for details.
- **Invalidation** — `CacheInvalidator::purgeAll()` is the single
  entry point for cache-wide flushes and is exercised by the
  `CacheManager` Livewire component, which stages destructive
  actions behind an in-component confirmation step. All destructive
  actions surface a status message on completion.

## Configuration Security

- All feature toggles are opt-in and default to `false`. There is no
  configuration state that ships enabled and would leak information
  by default.
- The dashboard route defaults to `admin/performance` (behind
  `web + auth + gate`); the JSON API defaults to `api/performance`
  behind rate limiting.
- No hardcoded secrets: a full grep of `src/` and `config/` for
  `api_key`, `secret`, `password`, `token`, `bearer` surfaced no
  literal credentials; every sensitive-looking key comes from an
  `env()` lookup or the config array itself.
- Sample rate for RUM defaults to `100` (accept every sample); the
  production recommendation in the README is to lower this to a
  representative sample.

## Code Hygiene

- No calls to `eval`, `assert` (string form), `shell_exec`, `exec`,
  `passthru`, `popen`, or `proc_open`.
- No calls to `die()`, `exit()`, `var_dump()`, or `print_r()`.
  (Enforced by `ArtisanPackUIStandard.Functions.DisallowedFunctions`
  in CI.)
- Exception messages surfaced through the RUM endpoint are opaque
  strings (`'storage-failed'`, `'unknown-metric'`); no exception
  payloads leak internal state to the client.
- `Log::channel(...)->debug(...)` is used throughout at debug level;
  no `Log::error` invocations expose stack traces.

## Findings

### Fixed — Moderate: Unserialize of cached payload without signature

**Location:** `src/Database/CachingEloquentBuilder.php` (query-cache
read path).

**Description:** The pre-1.0 code called
`unserialize( $hit, [ 'allowed_classes' => true ] )` on any string
returned from the cache backend. In the standard deployment shape
(dedicated cache backend, single-tenant) this is safe because the
package reads back what it wrote, but a shared or compromised cache
backend could feed a crafted serialized payload back to the process
and trigger PHP object injection.

**Resolution:** Added HMAC-based integrity verification. Cached
payloads are now prefixed with `perf:v1:<sha256-hmac>:<serialize>`
and verified with `hash_equals` on read; verification uses
`config('app.key')` as the signing key, and the cache key is folded
into the HMAC input (`hmac(key . "\0" . serialized, appKey)`) so a
signed entry cannot be relocated across keys on a shared backend.
`cacheSigningKey()` throws when `app.key` is empty rather than
falling back to a public constant — a fallback key defeats the
signing gate entirely for any misconfigured deployment. Tampered or
unsigned entries fail verification, are dropped from the cache, and
are transparently recomputed.

**Scope note:** the fix protects the *query cache read path only*
(`CachingEloquentBuilder::withQueryCache`). It does **not** close the
broader PHP object-injection surface introduced by Laravel's own
cache backends: file, database, and redis-serialize stores all
`serialize()` on `Cache::put(...)` and `unserialize()` on
`Cache::get(...)`, so an attacker with cache-write access can craft a
payload that hits `unserialize()` inside the framework before this
package's verifier runs. The HMAC prevents *forged CachingEloquentBuilder
payloads* from surviving verification; it does not prevent object
instantiation during Laravel's own read step. See the follow-up
recommendation below for the full close.

### Recommendation — Defensive gate check on destructive Livewire actions

**Location:** `src/Livewire/CacheManager.php`
(`flushAll`, `invalidate`, `invalidateByTag`, `warmCache`, etc.).

**Description:** The destructive actions on `CacheManager` currently
rely entirely on the surrounding route middleware and the
in-component confirmation step. If an application accidentally
exposes the component on a route without the intended `auth` /
`gate` stack (staging leaks, misconfigured middleware groups), a
lower-privilege user could invoke any action by dispatching a
Livewire call against the signed component snapshot they receive.

**Proposed follow-up:** Add an `AuthorizesDashboardActions` trait
that checks `Gate::allows( config('artisanpack.performance.dashboard.gate') )`
inside each destructive action, aborting with a status message when
the check fails. Deferring this to a 1.0.x point release keeps the
1.0 test surface stable and gives host applications time to opt in
to the gate name they want to use.

### Recommendation — Encryption on cache payloads to close the framework unserialize surface

**Location:**
`src/Database/CachingEloquentBuilder.php`,
`src/Cache/PageCacheManager.php`,
`src/Cache/FragmentCache.php`.

**Description:** All three cache paths hand structured PHP values to
Laravel's Cache API, which round-trips them through `serialize()` and
`unserialize()`. Under the standard threat model (dedicated cache,
single tenant) this is fine — the package reads back what it wrote.
Under a compromised or shared cache backend the framework-level
`unserialize()` is the object-injection surface, and it runs *before*
this package's HMAC verifier gets the payload. Signing after the fact
proves who wrote the bytes but does not prevent object instantiation
during the read.

**Proposed follow-up:** Wrap `Cache::put(...)` / `Cache::get(...)` at
each of the three paths with `Crypt::encryptString` /
`Crypt::decryptString`. Laravel's `Crypt` verifies the wrapped MAC
before it decrypts, so a tampered payload never reaches
`unserialize()`. Gate behind a
`artisanpack.performance.cache.encrypt` flag (default `false`) so
existing deployments migrate on their own schedule; when true, all
three paths encrypt on write and decrypt on read, and the entry
prefix changes so legacy signed-only entries fall through the same
verify-fails-drop-recompute path.

### Recommendation — Rate-limit the raw metric write path more aggressively

**Location:** `config/performance.php` under `routes.api_throttle`.

**Description:** The default of `60,1` (60 requests per minute)
comfortably covers legitimate RUM traffic per client but is a
plausible amplification vector against `performance_raw_metrics`
when `monitoring.store_raw_metrics = true`. Applications enabling
raw storage in production should tighten the throttle (`20,1` per
IP is a common pick) or add a per-user throttle key when the
endpoint is placed behind auth.

**Proposed follow-up:** Update the README's monitoring section to
document the tuning knob explicitly and link to
`throttle:` middleware customization.

## Sign-off

Everything on the tracking checklist has been reviewed. Nothing on
this list is a shipping blocker for 1.0. The single moderate finding
(unserialize hardening) landed in this same audit pass; the three
recommendations are tracked here so the 1.0.x planning discussion
has an easy reference point.
