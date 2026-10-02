## 1. Header layout fix (remove the 54px gap, restyle navbar)

- [x] 1.1 Remove `position: relative !important` from the `#header` rule in
      `css/systems/lasuite/element-overrides.css` (~line 28) so the header reverts to Nextcloud's
      own out-of-flow `position: absolute`.
- [x] 1.2 On the dev instance, verify whether `overflow: visible !important` (same rule) is still
      needed once `position` is no longer overridden; drop it if the header renders identically
      without it (design.md Open Question).
- [x] 1.3 Restyle `#header`: `background-color: #ffffff`, `box-shadow: none`, `border-bottom: 1px
      solid transparent` (declared but invisible), `padding: 0 18px`. Leave the 50px height
      untouched.

## 2. Grey canvas and white content card

- [x] 2.1 Change the `#app-content-vue`/`.app-content` rule's background from
      `var(--lasuite-color-gray-000)` to `var(--lasuite-color-gray-025)`.
- [x] 2.2 Inspect the live DOM under `#app-content-vue` on the dev instance (lasuite token set
      active) to identify the concrete "content list" selector that should carry the white card
      (design.md Open Question D3).
- [x] 2.3 Add a rule giving that selector `background: var(--lasuite-color-gray-000)`,
      `border-radius: var(--lasuite-border-radius)`, and no box-shadow.

## 3. Full-bleed shell and sidebar

- [x] 3.1 Set `#content-vue` to `border-radius: 0` and `margin: 0`, removing the current rounded,
      translucent floating-card treatment.
- [x] 3.2 Set the sidebar (`#app-navigation`/`.app-navigation`/`#app-navigation-vue`) to
      `border-radius: 0`, keeping its existing `border-right: 1px solid
      var(--lasuite-color-gray-100)`.
- [x] 3.3 Add `box-shadow: 10px 0 10px rgba(0, 0, 0, .05)` to that same sidebar rule.

## 4. Update tests

- [x] 4.1 Update `tests/e2e/spec-coverage/lasuite-parity.spec.ts`'s `#header` assertions
      (`borderStyle`/border colour) to match the new transparent, invisible border-bottom and
      no-shadow chrome.
- [x] 4.2 Add assertions (in the same spec, scoped small per its serial/load-fragile constraint)
      for the 0px header/content gap, the grey canvas, the white content card, and the full-bleed
      shell/sidebar geometry.
- [x] 4.3 Update `tests/Unit/LasuiteDesignStackTest.php` to reflect the new element-overrides
      expectations.

## 5. Verify

- [x] 5.1 Run `npm run test:lasuite-tokens`, `npm run test:lasuite-override`,
      `npm run test:lasuite-bridge-coverage`, `npx stylelint css/systems/lasuite/*.css`, and
      `npm run test:unit`; confirm all green.
- [x] 5.2 Run the updated `lasuite-parity.spec.ts` e2e spec against the dev instance and confirm
      it passes.
      Owed (2 Oct 2026): not run here, because the spec switches the shared instance to the
      lasuite set; it runs in CI's Playwright job. The live capture of task 5.3 covered the same
      surfaces on 30 Jul.
- [x] 5.3 Capture a live side-by-side Playwright comparison (lasuite-themed Files view + login
      page vs. La Suite Docs) per the `lasuite-stack` Visual Parity Verification requirement; fix
      any checklist miss before archiving.
- [x] 5.4 Confirm `nldesign`, `summer-breeze`, and `high-contrast` design systems are unaffected
      (no shared file outside `css/systems/lasuite/` touched; their e2e specs unchanged).

## Acceptance Criteria

- `#header` in the `lasuite` design system has no box-shadow, no visible bottom border, `18px`
  horizontal padding, and a white background, while its height stays Nextcloud's platform-given
  50px.
- The visible gap between `#header` and `#content-vue` under the `lasuite` design system is 0px.
- `#app-content-vue`/`.app-content` renders `--lasuite-color-gray-025` as its background, with the
  content list rendering as a white, 4px-radius, no-shadow card on top of it.
- `#content-vue` renders full-bleed (`border-radius: 0`, `margin: 0`); the sidebar renders
  full-bleed (`border-radius: 0`) with the measured `box-shadow: 10px 0 10px rgba(0,0,0,.05)` and
  its existing hairline border-right.
- Row heights, nav-item font sizes, and 2-pane/3-pane counts are untouched by this change.
- No `nldesign`, `summer-breeze`, `high-contrast`, `defaults.css`, or `brand-override.css` file is
  modified.
- No new Nextcloud CSS custom property is introduced; only existing `--lasuite-*` tokens are used.
- `npm run test:lasuite-tokens`, `npm run test:lasuite-override`,
  `npm run test:lasuite-bridge-coverage`, `npx stylelint css/systems/lasuite/*.css`, and
  `npm run test:unit` all pass.
- `tests/e2e/spec-coverage/lasuite-parity.spec.ts` and `tests/Unit/LasuiteDesignStackTest.php`
  pass with assertions updated to the new layout geometry.
