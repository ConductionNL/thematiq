# Tasks: La Suite shell geometry (#182)

## 1. Spec
- [x] 1.1 Write proposal and the `lasuite-stack` delta.

## 2. Server
- [x] 2.1 `DesignSystemService::getDesignSystem()` appends `versioned_stylesheets[<running major>]` to `stylesheets`; `designSystemLayers()` emits the list unchanged.
- [x] 2.2 The return shapes document the optional `versioned_stylesheets` key (psalm strict).

## 3. Stylesheets
- [x] 3.1 `css/systems/lasuite/shell-nc35.css` sets `--header-height: 64px`, with the La Suite Docs reference in its header.
- [x] 3.2 Declare it under `versioned_stylesheets["35"]` for `lasuite` only.
- [x] 3.3 `element-overrides.css`: `#content` clears `var(--header-height)`; the search field centres on it.

## 4. Tests
- [x] 4.1 PHPUnit `tests/Unit/Service/LasuiteShellGeometryTest.php` (red first): manifest order, unknown major, lasuite only, files exist, header height, no 50px literals.
- [x] 4.2 Playwright `tests/e2e/spec-coverage/lasuite-shell-geometry.spec.ts`: header height, content offset, search centring, navigation width, version scoping, cunningham unaffected.

## 5. Verify
- [x] 5.1 Run the Playwright spec against a Nextcloud 35 instance with the `lasuite` set (owed live).
      Ran 2026-10-09 on a throwaway NC 34 instance (:8098): 6 of 6 pass, which on 34 proves the shell layer stays off and the header keeps its stock height.
      Ran 2026-10-10 on a throwaway Nextcloud 35.0.1 (`nextcloud:35`, compose project thematiq-live35, :8094, live-wt mounted): 6 of 6 pass. Checked by hand as well: with `lasuite` active the page links `css/systems/lasuite/shell-nc35.css` and the header is 64px, the navigation 300px; with `cunningham` and with `nextcloud` the shell sheet is absent and the header keeps 44px.
- [x] 5.2 Record screenshot baselines per supported Nextcloud major (follow-up).
      Moved 2026-10-09 to `token-set-coverage-next-waves` task 6.1 (decision 126, Q-thematiq-2): follow-up work this change called out of scope.
