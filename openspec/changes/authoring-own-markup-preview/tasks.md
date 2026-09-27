# Tasks: preview your own components with the house style

Tick a box when the work is merged to `development`, not when it is started.
This change lands after the chip row, stage and hash tasks of `openspec/changes/component-playground`.

## 1. Answer the open question first

- [ ] 1.1 On a running instance, add a throwaway `<iframe sandbox="allow-same-origin" srcdoc="...">`
      to the theming panel and load it in Firefox, Chromium and Safari under Nextcloud's page policy.
      Record per browser whether it renders and whether the console reports a `frame-src`
      violation. If any browser blocks it, switch design decision 3 to the shadow root fallback
      before task 2. Verify: the result table is in design.md.

## 2. Frame and sanitiser

- [ ] 2.1 Add `js/lib/markupSanitizer.js`, dual-mode like `js/lib/layerSwap.js`, with the allowlist
      and the removal report of design decision 4. Verify: `npm run test:unit` on
      `tests/vitest/markupSanitizer.spec.js`: javascript links, `on*` handlers, `script`, `iframe`,
      `meta`, `base`, `form`, external `src`, `url()` in `style`, and one case that must come
      through unchanged.
- [ ] 2.2 Build the frame in `js/playground.js`: fixed `sandbox="allow-same-origin"`, the `srcdoc`
      policy, the font rules. Verify: `npm run test:unit` on
      `tests/vitest/playgroundOwnComponent.spec.js`, which fails if `allow-scripts` appears or the
      policy string changes.
- [ ] 2.3 The token bridge: scan `var(--` names, copy values from the preview container with
      `readVar()`, repaint on every playground edit. Verify: vitest `testBridgeCopiesScannedNames`
      and Playwright scenario "An unsaved colour edit repaints the frame".

## 3. Stage and token list

- [ ] 3.1 The "Your component" chip in every tab, the stage with HTML and CSS fields, the removal
      report, the "Scripts do not run here" line. Verify: Playwright
      `tests/e2e/spec-coverage/own-component-preview.spec.ts`, scenarios "An administrator previews
      a card they are building", "A pasted script does not run", "A pasted image cannot call home".
- [ ] 3.2 Filter the token list to the scanned names, with the read-only note for names the editor
      cannot write. Verify: Playwright scenario "The token list follows the pasted code".
- [ ] 3.3 The light and dark switch, and `playgroundDarkTokens` in
      `PlaygroundStateService::getInitialState()`. Verify: PHPUnit
      `PlaygroundStateServiceTest::testDarkTokensPublished`, and Playwright scenario "A builder
      checks the card in dark mode".
- [ ] 3.4 Keep the playground working when the stage throws. Verify: vitest
      `testThrowingOwnStageLeavesPlaygroundWorking`.

## 4. Saving

- [ ] 4.1 Add `lib/Service/OwnComponentService.php` (IAppData folder `playground-components`, 20
      components, 64 KB each, slug rule). Verify: PHPUnit `OwnComponentServiceTest::testLimits`,
      `testSlugRule`, `testStoredRawAndCleanedOnRender` (the stored text is raw, the rendered text
      is cleaned).
- [ ] 4.2 Add `lib/Controller/OwnComponentController.php` with list, save and delete, all
      `#[AuthorizedAdminSetting(Admin::class)]`, generic error texts. Verify: PHPUnit
      `OwnComponentControllerTest`, and `curl -u admin:$ADMIN_PASSWORD
      http://localhost:8080/apps/thematiq/settings/playground/components` returns 200.
- [ ] 4.3 The chip menu of saved names and the `#preview={tab}/own-{slug}` hash in `parseHash()`.
      Verify: vitest `testOwnSlugHashParsed`, `testUnknownOwnSlugIgnored`, and Playwright scenario
      "An administrator reopens a saved card from a link".
- [ ] 4.4 Newman: 200, 400, 401 and 403 for the three routes, credentials from environment
      variables. Verify: `tests/integration/run-newman.sh`.

## 5. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [ ] 5.1 Localisation: the chip, field labels, switch, notes, removal report and every error text
      in `l10n/en.json` and `l10n/nl.json`, domain `thematiq`. Verify: `npm run test:l10n`.
- [ ] 5.2 Documentation: a "Preview your own component" section in `docs/features/token-editor.md`
      with a screenshot, the three locks and what does not run. Verify: `npm run build` in `docs/`.
- [ ] 5.3 WCAG AA contrast: the stage shows the contrast of the scanned text and background pairs it
      can pair by name (`-text` on its base), through `ContrastService` via
      `POST /apps/thematiq/api/contrast/evaluate`. Verify: Playwright pastes a card with
      `--nldesign-color-primary` text on `--nldesign-color-primary-light` and sees the verdict.
- [ ] 5.4 Dark mode: covered by task 3.3.
- [ ] 5.5 Incomplete token sets: a scanned name the set does not declare shows the default from
      `defaults.css`, or "not set" when no layer declares it. Verify: Playwright on a set that
      lacks `--nldesign-color-primary-light`.
- [ ] 5.6 Accessibility: the fields have visible labels, the frame has a `title` naming the
      component, the switch is a real toggle with state, and the removal report is announced in a
      `role="status"` region. Verify: `npm run test:unit` on `tests/vitest/admin-a11y.spec.js`.
- [ ] 5.7 Security review: the three locks, the size limits, no audit entry, admin-only routes.
      Verify: a reviewer who did not write the code signs off in the PR, and
      `composer check:strict` is green.

## 6. Before the pull request

- [ ] 6.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`.
- [ ] 6.2 On a running instance, paste a real component from a municipal design system, edit two
      tokens, switch to dark, save it, reopen it from its link, and record what was seen in the PR
      body.
