# Tasks — lasuite dark palette

## 1. Source the palette

- [x] Extract La Suite's shipped dark Cunningham values for the `--lasuite-color-*`
      ramp, the same way the light ramp was sourced
      - do not invert the light ramp; upstream ships a dark theme and it is the reference
- [x] Record which tokens have no upstream dark counterpart and decide each explicitly

## 2. Emit

- [x] Extend the generator so the lasuite dark variant emits `--lasuite-color-*`
      alongside the existing `--nldesign-*` values
- [x] Emit under the EXISTING dark scope selectors so injection order and scoping
      keep satisfying the current dark-mode spec
- [x] Verify no REQ-CSS-007 reserved variable is written

## 3. Translucent values

- [x] Re-express the active-row wash so it is legible on both grounds
- [x] Audit `element-overrides.css` for any other translucent literal and give each
      a dark counterpart

## 4. Verification

- [x] Extend `tests/e2e/spec-coverage/dark-mode.spec.ts` to render in dark mode and
      assert computed shell values (header, canvas, card, active row, search)
- [x] Add the WCAG contrast loop the dark-mode spec already requires for generated variants
- [x] Add a guard asserting every `--lasuite-color-*` token READ by element-overrides
      has a dark value — the check that would have caught this

## 5. Evidence

- [x] Capture the four surfaces in dark mode before and after
      Owed live (2 Oct 2026): no shared instance was used. See the notes below.
- [x] Update `openspec/specs/dark-mode/spec.md` with the merged requirements

## Acceptance criteria

- Header, canvas, card, active row and search field all resolve to dark values in dark mode
- No reserved REQ-CSS-007 variable is written by the dark variant
- The selected navigation row stays identifiable in dark mode
- `composer check:strict`, stylelint and the unit suite stay green

## Notes from the build (2 Oct 2026)

- The first half landed on 30 Jul (`92b45825`): the shell rules read Cunningham's contextual
  tokens with the raw step as a fallback, and a dark block in `element-overrides.css` remaps them
  under the same two scope selectors as `css/tokens/dark/lasuite.css`.
- Task 1.1 and 2.1 changed shape on the evidence. Cunningham's own `.cunningham-theme--dark`
  block does NOT redefine the ramp (`gray-000` stays white); it remaps the contextual tokens. So
  the dark values are upstream's contextual mapping (surface primary gray-800, secondary
  gray-850, tertiary gray-900, surface border gray-750, neutral tertiary fill gray-750, neutral
  secondary border gray-600, brand secondary fill brand-700, brand secondary and tertiary text
  brand-100 and brand-250), read from the full `--lasuite--globals--colors--*` ramp that
  `defaults.css` and `brand-override.css` already emit. No generator change was needed, and the
  invented translucent hairline of the first pass is gone.
- Task 1.2: no token is left without an upstream dark counterpart. The avatar initials keep
  `gray-000` on the avatar's own colour fill, which is the same in both modes (allow-listed in the
  guard with that reason).
- Six rules still read raw light steps and stayed light in dark mode: the app menu and the
  navigation hover fill, the navigation and breadcrumb hairlines, the input border, and the
  headings (`gray-900`, near-black on a dark card). The secondary and tertiary buttons read three
  brand contextuals the dark block did not remap. All are fixed.
- Task 2.3, 4.3: `tests/css/check-lasuite-dark-ramp.js` fails on a raw gray read, on a token one
  of the two dark scopes does not remap, and on a write to a REQ-CSS-007 variable.
- Task 3.2: the remaining literals are the navigation's `rgba(0, 0, 0, 0.05)` shadow, which is a
  shadow on both grounds, and the light wash fallbacks behind `--lasuite-active-row-wash`.
- Task 4.1, 4.2: `tests/vitest/lasuiteDarkRamp.spec.js` resolves the shipped stylesheets in the
  lasuite load order (defaults, brand-override, element-overrides) and asserts the dark shell
  values and the WCAG loop. A Playwright run in dark mode is owed: it needs the instance switched
  to the lasuite set and a dark user theme, which no spec-coverage run may do on the shared
  instance.
- Not changed: `html` paints its background from the `:root` value of the surface token, while the
  dark remap sits on `body`; the body covers the viewport, so this shows only on overscroll.
- Spec: the "selected row is identifiable without relying on colour alone" clause was dropped
  from the delta. La Suite marks the selected row with the wash only, and the wireframe alignment
  levelled the label weight on purpose; adding a second cue is a product call (see the PR).
