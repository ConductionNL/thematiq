# Tasks: component-scoped tokens

Tick a box when the work is merged to `development`, not when it is started.

## 1. Spec and design

- [x] 1.1 Write this change: `proposal.md`, `design.md`, `tasks.md`, spec deltas on
      `component-tokens`, `css-architecture`, `admin-settings`, `token-editor-ui`,
      `component-playground`.
- [x] 1.2 Record why the variable is re-scoped rather than the component repainted, and leave
      the rejected `!important`-per-component approach in the design so the trade is not
      re-made (design decision 1).
- [x] 1.3 Record the `--thematiq-global-*` capture and the cycle it exists to avoid, against
      the one `StockTokensService` already documents (design decision 2).

## 2. The mapping table

- [x] 2.1 `scripts/mapping/component-tokens.json`: per component the selectors it occupies,
      per token the global it replaces, its label, type, `primary` flag, and the `paints` /
      `callout` the playground chip renders.
- [x] 2.2 `brandTokens`: the 56 globals, generated from the table rather than hand-listed, and
      asserted equal to the hand-written registry in `TokenRegistry.php`.
- [x] 2.3 Leave `--color-main-background` out. `overrides.css` marks it "intentionally not
      overridden — overriding breaks dark mode", so no component token may claim it.

## 3. The stylesheets

- [x] 3.1 `scripts/generate-component-scopes.mjs`, with `--check`, following the
      `generate-guest-css.mjs` contract. Registered as `generate:component-scopes` /
      `test:component-scopes`.
- [x] 3.2 The generator refuses to emit when one component maps two tokens onto one global.
- [x] 3.3 `css/component-scopes.css` and `css/primary-lock.css`, generated and committed, and
      added to `.prettierignore` beside the other drift-checked generated files.
- [x] 3.4 `css/admin.css`: `.nldesign-token-row--locked`.

## 3a. The rules that already paint these components

A re-scoped variable is inert wherever thematiq already paints the property itself with
`!important`, because the shipped rule never reads the variable. Each of these was found by
cross-referencing the mapping against every `var(--nldesign-*)` in the two stylesheets, and
each fix keeps the previous token as the fallback so no shipped token set changes.

- [x] 3a.1 `theme.css` login button: read `--nldesign-component-login-button-*` first, falling
      back to `--nldesign-component-button-primary-action-*`. This is the case the whole
      change was reported for — the login button could not be moved without moving the main
      primary button, because both rules read the same token.
- [x] 3a.2 `theme.css` checked checkbox/radio: `--nldesign-component-checkbox-checked-background-color`
      before `--nldesign-color-primary`.
- [x] 3a.3 `theme.css` `.icon-loading`: `--nldesign-component-progress-background-color`
      before `--nldesign-color-primary`.
- [x] 3a.4 `#header` background and the six header-glyph rules: the component token before
      `--nldesign-color-header-background` / `--nldesign-color-header-text`, which a token
      set owns.
- [x] 3a.5 Headings: `defaults.css` derives all six per-level `-color` and `-font-weight`
      tokens from one `--nldesign-component-heading-color` / `-font-weight`, so the chip's
      single row moves h1–h6 while a set overriding one level still wins. `theme.css` gains
      the heading and paragraph `font-family` rules the chip's typeface row needs.
- [x] 3a.6 Table: use `--nldesign-component-table-border-color` and
      `--nldesign-component-table-row-hover-background-color`, the names `theme.css` already
      consumes, rather than inventing near-synonyms it would have ignored.
- [x] 3a.7 The shared geometry rules, split by component. There were TWO copies of the same
      blanket list — `theme.css`'s "REMOVE ALL ROUNDED CORNERS" and
      `element-overrides.css`'s "BORDER RADIUS CONSISTENCY" — and because element-overrides
      loads last, splitting theme.css alone changed nothing. Both are now one rule per
      component, each reading its own token with `--nldesign-border-radius` behind it, so a
      sharp theme squares off exactly as before while the Corner rows finally do something.
      Same treatment for `.toastify.dialogs`, `#body-login .wrapper`, `.login-box` and
      `#body-login button`, which each forced the brand radius onto one component.
- [x] 3a.8 `element-overrides.css` label rule: `button[class*='primary'] …` painted every
      primary button's label AND every descendant from the BRAND token
      `--nldesign-color-primary-text`, at a specificity the scope layer cannot outrank. It
      now reads `--nldesign-component-button-primary-action-color` first — which is what
      finally makes the login button's Label row work, since the alias redirects that token
      inside the login button.
- [x] 3a.9 `#header .header-appname` read the brand primary; it now follows
      `--nldesign-component-header-color`.
- [x] 3a.10 Aliases for the three remaining shadowed families found by the audit:
      secondary-button's corner (its rule reads the shared button radius), primary-button's
      label weight (its rule reads a primary-action weight token the chip does not own), and
      textarea's border and corner (a textarea is styled by the TEXTBOX rules, one selector
      list covering `input` and `textarea` together).

## 3b. The guard

- [x] 3b.1 `tests/vitest/componentTokenReach.spec.js`. For every component it attributes the
      shipped rules to a chip, follows each token's chain through `defaults.css` and the
      mapping's `aliases`, and fails on any chip row a rule paints over. This is the defect
      class the login button shipped with — a control that renders, saves and does nothing —
      and nothing detected it before.
- [x] 3b.2 Two allowlists, both asserted non-stale so they cannot quietly absorb new
      breakage: `BY_DESIGN` (`--nldesign-color-on-surface`, the WCAG pairing mechanism, which
      is meant to travel by inheritance rather than be per-component) and `NO_ROW_YET` (four
      properties no chip offers a row for — see 8.7).

## 4. The registry

- [x] 4.1 `TokenRegistry::getComponentTokens()` reads the table; `getBrandTokens()` wraps the
      four hand-written methods with `group` / `primary`; `getTokens()` merges them.
- [x] 4.2 The `primary` flag travels on each registry entry, so the locked control and the
      locked value are decided by one flag in one file. No separate "which tokens are locked"
      accessor: PHP never needs the list, and a second way to ask the question is a second
      way for the two to disagree.
- [x] 4.3 A missing or malformed table degrades to an empty component layer, not an error.
- [x] 4.4 `TokenRegistryInterface`: the return shape gains `group` and `primary`.

## 5. The cascade

- [x] 5.1 `CssInjectionService::inject()` emits `component-scopes` after the design-system
      layers and before the custom overrides.
- [x] 5.2 `injectConditionalStyles()` emits `primary-lock` last, so it outranks a
      per-component value stored in `custom-overrides.css`.

## 6. The toggle

- [x] 6.1 `primary_drives_components` appconfig, default `0`.
- [x] 6.2 `SettingsController::setPrimaryDrivesComponentsSetting()` + route, audited through
      `toggle_changed` like its siblings.
- [x] 6.3 `Settings/Admin.php` + `templates/settings/admin.php`: the checkbox, with the other
      admin toggles rather than inside the playground.
- [x] 6.4 `js/admin.js`: save, swap the `primary-lock` layer live, and lock the affected rows
      in place without discarding unsaved edits or resetting the open tab.
- [x] 6.5 `ConfigBundleService`: export, validate and apply, so a bundle round-trips it.
- [ ] 6.6 Decide whether `Capabilities` should carry it. Currently NOT added — the payload is
      a pinned eight-key contract and this setting changes nothing a client renders.

## 7. The playground

- [x] 7.1 Repoint all 34 chips onto component tokens; bump `components.json` to version 4 and
      rewrite its `$comment` to say what the tokens now are.
- [x] 7.2 `playgroundInventory.spec.js`: read the component layer from the table, exempt the
      brand globals from the coverage rule, and add the inverse guard — no chip may name a
      global.
- [x] 7.3 Replace the "reads a token filed under another tab, and does" assertion, which
      pinned a symptom of chips naming globals, with the invariant that replaced it.
- [x] 7.4 `playgroundSelection.spec.js`: the primary button's first callout names the button's
      own background token.

## 8. Verification

- [x] 8.1 `npm run test:component-scopes` — the committed stylesheets match the table.
- [x] 8.2 `npx vitest run tests/vitest/playgroundInventory.spec.js tests/vitest/playgroundSelection.spec.js`.
- [x] 8.3 `npx stylelint` on both generated stylesheets.
- [x] 8.4 l10n: `check-l10n`, `check-l10n-completeness`, `l10n:build` for the four new strings
      (two in `js/admin.js`, two in the template).
- [ ] 8.5 PHP gates — `phpcs`, `phpstan`, `psalm`, `phpunit`. NOT RUN: no PHP on the authoring
      machine and none in WSL, so every PHP file in this change is unverified beyond review.
- [x] 8.6 `npx vitest run tests/vitest/componentTokenReach.spec.js` — no chip row is painted
      over by a shipped rule.
- [ ] 8.7 FOUR PROPERTIES STILL HAVE NO ROW, recorded in the guard's `NO_ROW_YET`. Each needs
      a token added rather than a rule fixed, which is a separate decision:
      the navigation panel's own background (its variable is `--color-main-background`, which
      overrides.css deliberately leaves alone, so it needs a token the nav rule reads
      directly rather than a re-scope); the text colour inside a text input and inside a
      textarea; and the primary button's border colour, which tracks its background in every
      shipped set but is a separate token.
- [ ] 8.8 Confirm in a browser that moving `Login button` leaves `Primary button` alone, that
      the Corner rows now move their component, and that turning the toggle on greys the
      colour rows out and repaints them to the brand primary.
