---
kind: code
---

## Why

The `custom-css-overrides` capability (`openspec/specs/custom-css-overrides/spec.md`, `status: done`)
gives admins a token editor: it writes `--color-*` custom properties into a single `:root` block in
`custom-overrides.css`, nothing else. That is deliberate — it is a structured, machine-generated
file with a strict round-trip format the editor UI depends on. But it means an admin who needs a
selector-level tweak (a component's border-radius, a `@media` breakpoint rule, a one-off class
override for a screen a token can't reach) has no supported path — the only alternative today is
editing the app's shipped CSS files directly, which gets overwritten on every update. Municipalities
running nldesign regularly hit small presentation gaps the 40+ shipped token sets don't cover, and
currently file them as feature requests against the token set itself. This change adds a second,
separate admin-editable layer for arbitrary (sanitised) CSS so those gaps can be closed locally
without waiting on an nldesign release.

## What Changes

- Add a new `css/custom-css.css` file: freeform, admin-authored CSS, loaded **after**
  `custom-overrides.css` so it wins the cascade over both the token editor and every design-system
  layer beneath it.
- Add an appconfig flag `custom_css_enabled` (default `'0'`) — the freeform layer is off by default;
  an empty or absent file must not affect the CSS stack (mirrors the existing "missing file does not
  break stack" guarantee in `custom-css-overrides`).
- Add `CustomCssValidator`, a standalone, unit-testable sanitisation service that rejects (with a
  per-rule error) `@import`/`@charset`, external `url(...)` schemes, script-execution vectors
  (`expression(`, `behavior:`, `-moz-binding:`), style/script breakout strings, oversized input, and
  unbalanced braces — see design.md for the full rule set and rationale.
- Add `CustomCssService` (read/write/validate, atomic write) and wire a new endpoint pair into
  `OverridesController` (or a sibling controller — see design.md) behind
  `#[AuthorizedAdminSetting(Admin::class)]`, auditing every save via the existing `ThemingAuditService`.
- Add a new hook point in `CssInjectionService::inject()` immediately after the existing
  `custom-overrides` emit, gated on `custom_css_enabled`.
- Extend `templates/settings/admin.php` with a freeform CSS textarea, an enable/disable toggle, and
  inline validation error display (vanilla JS, matching the existing admin UI's non-Vue pattern).
- Enforce that freeform CSS can never set the REQ-CSS-007-reserved Nextcloud dark-mode variables
  (`--color-main-background*`, `--color-background-plain`, `--background-invert-if-*`) — see
  design.md for the enforcement mechanism.

## Capabilities

### New Capabilities
- `custom-css-freeform`: admin-editable freeform CSS file, loaded last in the cascade, gated by an
  appconfig flag, protected by a dedicated sanitisation service and full audit logging.

### Modified Capabilities
- None. `custom-css-overrides` is not touched — see rationale below.

## Impact

- **Why a new capability instead of extending `custom-css-overrides`**: that spec's `File Format`
  requirement is a hard contract — "MUST contain only a single `:root` block... MUST NOT contain
  selectors other than `:root`, media queries" — enforced by `CustomOverridesService::writeFile()`
  and consumed by the token editor's round-trip parser (`CssParserService::parseRootBlock()`).
  Freeform CSS is arbitrary selectors and `@media` blocks by definition, so it cannot satisfy that
  requirement without either weakening it (breaking the token editor's parse contract and its
  `status: done` guarantee) or overloading one file with two incompatible write paths and two
  incompatible sanitisation regimes. A new capability with its own file, its own spec, and its own
  validator keeps both contracts intact and independently testable.
- **Code**: `lib/Service/CustomCssService.php` (new), `lib/Service/CustomCssValidator.php` (new),
  `lib/Service/CssInjectionService.php` (new hook, ~line 245-246 region), `lib/Controller/OverridesController.php`
  or a new controller (new endpoints), `templates/settings/admin.php` (new UI section),
  `appinfo/routes.php` (new routes), `css/custom-css.css` (new, gitignored generated file, mirroring
  `custom-overrides.css`'s treatment).
- **No OpenRegister schemas** are involved — this capability persists to a flat CSS file plus one
  appconfig key via `IConfig`, exactly like `custom-css-overrides`; no Seed Data section applies.
- **No lifecycle/aggregation/notification behaviour** is introduced — the ADR-031
  declarative-vs-imperative notification-dialect distinction does not apply to this change.
- **Dependencies**: none beyond existing services (`IAppManager`, `IConfig`, `ThemingAuditService`,
  `CssParserService` for brace/structure checks if reused).
