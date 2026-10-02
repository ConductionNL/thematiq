# Tasks: La Suite shell geometry (#182)

## 1. Spec
- [x] 1.1 Write proposal and the `lasuite-stack` delta.

## 2. Server
- [x] 2.1 `CssInjectionService::versionScopedStylesheets()` reads `versioned_stylesheets` for the running major and `designSystemLayers()` emits them after the design-system stylesheets.
- [x] 2.2 `versionScopedStylesheets()` reads the optional key defensively (array<string, mixed>), so the `DesignSystemService` return shapes stay as they are.

## 3. Stylesheets
- [x] 3.1 `css/systems/lasuite/shell-nc35.css` sets `--header-height: 64px`, with the La Suite Docs reference in its header.
- [x] 3.2 Declare it under `versioned_stylesheets["35"]` for `lasuite` only.
- [x] 3.3 `element-overrides.css`: `#content` clears `var(--header-height)`; the search field centres on it.

## 4. Tests
- [x] 4.1 PHPUnit `tests/Unit/Service/LasuiteShellGeometryTest.php` (red first): manifest order, unknown major, lasuite only, files exist, header height, no 50px literals.
- [x] 4.2 Playwright `tests/e2e/spec-coverage/lasuite-shell-geometry.spec.ts`: header height, content offset, search centring, navigation width, version scoping, cunningham unaffected.

## 5. Verify
- [ ] 5.1 Run the Playwright spec against a Nextcloud 35 instance with the `lasuite` set (owed live).
- [ ] 5.2 Record screenshot baselines per supported Nextcloud major (follow-up).
