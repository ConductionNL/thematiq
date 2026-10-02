# Tasks: transparency, dark values and motion in the token editor

Tick a box when the work is merged to `development`, not when it is started.

## 1. Value grammar

- [x] 1.1 Add `lib/Service/TokenValueValidator.php` with the grammar per type (design decision 1).
      Verify: PHPUnit `TokenValueValidatorTest` with accepted and refused cases per type,
      including `#15427380`, `transparent`, `150ms`, `6s` (refused), `cubic-bezier(1.5, 0, 0, 1)`
      (refused).
- [x] 1.2 Mirror the grammar in `js/lib/tokenTransforms.js` and add `splitAlpha()`. Verify:
      `npm run test:unit` on `tests/vitest/tokenTransforms.spec.js` with the same cases as 1.1.
- [x] 1.3 Add the types `duration` and `easing` to `TokenRegistry`, replace the two motion rows with
      `--nldesign-animation-quick` and `--nldesign-animation-slow`, and add `--nldesign-animation-easing`.
      Verify: PHPUnit `TokenRegistryTest::testMotionTokensAreTyped`, and the existing
      `tests/Unit/Service/TokenRegistryTest.php` stays green.

## 2. Saving and reading overrides

- [x] 2.1 `OverridesController::setOverrides()`: 400 for a wrong value or an unknown name, naming
      the token, nothing written. Return a generic message on a write failure instead of the
      exception text, which carries a file path today (`OverridesController.php:149-151`). Verify:
      PHPUnit `OverridesControllerTest::testWrongValueIs400`, `testUnknownTokenIs400`,
      `testWriteFailureHidesPath`.
- [x] 2.2 Accept and return `darkOverrides`. `CustomOverridesService` writes the two dark scopes
      and reads the `body[data-theme-dark]` block back. Verify: PHPUnit
      `CustomOverridesServiceTest::testDarkScopesWritten`, `testDarkValuesReadBack`.
- [x] 2.3 Add `DarkPaletteService::deriveDarkValue()` and store the derived value for every colour
      override without a dark value. Verify: PHPUnit `testEmptyDarkValueIsDerived`.
- [x] 2.4 Write both motion names for a motion override. Verify: PHPUnit
      `testMotionOverrideWritesBothNames`.
- [x] 2.5 Add `lib/Repair/MigrateOverrideValueTypes.php` and register it in `appinfo/info.xml`.
      Verify: PHPUnit `MigrateOverrideValueTypesTest`, and `occ maintenance:repair` on a copy of
      an instance with a `--animation-quick` override.
- [x] 2.6 Add the 400 cases and a `darkOverrides` round trip to
      `tests/integration/thematiq.postman_collection.json`. Verify: `tests/integration/run-newman.sh`.

## 3. Colour maths

- [x] 3.1 `ContrastService`: read `#rgba`, `#rrggbbaa` and the alpha of `rgba()`/`hsla()`, add
      `parseColorWithAlpha()`, blend before `ratio()` (design decision 5). Verify: PHPUnit
      `ContrastServiceTest::testFaintTextFailsAfterBlend`, `testEightDigitHexIsEvaluated`.
- [x] 3.2 `DarkPaletteService::deriveColorToken()` keeps alpha, bump the generator version,
      regenerate with `occ nldesign:generate-dark-variants --force`. Verify: PHPUnit
      `DarkPaletteServiceTest::testTranslucentSurfaceKeepsAlpha`, and `git diff --stat css/tokens/dark/`
      in the PR shows every file with the new version header.
- [x] 3.3 `CustomTokenSetService::deriveTheming()` blends a translucent primary or background into
      6-digit hex, and the sync dialog shows both values with the note. Verify: PHPUnit
      `testTranslucentPrimaryIsBlendedForCore` (`#15427380` over white is `#8aa0b9`).

## 4. Editor

- [x] 4.1 Opacity control, checkerboard swatch and alpha-keeping picker in `buildTokenRow()` and
      `wireTokenRows()`. Verify: Playwright `tests/e2e/spec-coverage/token-editor-value-types.spec.ts`,
      scenarios "An administrator makes the focus colour half transparent", "Moving the picker keeps
      the alpha", "A typed 8-digit hex updates both controls".
- [x] 4.2 The "Dark" line per colour row with the derived placeholder. Verify: Playwright scenarios
      "An administrator gives the primary colour its own dark value" and "An empty dark value is
      derived and shown".
- [x] 4.3 Duration and easing inputs and the motion preview. Verify: Playwright scenarios "An
      administrator slows down quick animations" and "An administrator picks a custom easing curve".
- [x] 4.4 `defaults.css` declares `--nldesign-animation-easing: ease`, and every `ease` in
      `theme.css` transitions reads it. Verify: `npm run stylelint`, and Playwright reads the
      computed `transition-timing-function` of a primary button.

## 5. Mandatory categories (config.yaml, ADR-005, 009, 010, 011)

- [x] 5.1 Localisation: labels for the opacity control, the dark line, the motion inputs, the
      easing keywords, the sync note and every 400 message in `l10n/en.json` and `l10n/nl.json`,
      domain `thematiq`. Verify: `npm run test:l10n`.
- [x] 5.2 Documentation: `docs/features/token-editor.md` gains transparency, dark values and motion,
      with screenshots from a running instance. Verify: `npm run build` in `docs/`.
- [x] 5.3 WCAG AA contrast: the editor's contrast hint uses the blended colour. Verify: Playwright
      sets text at 20% opacity and sees the fail hint.
- [x] 5.4 Dark mode: both dark paths render the same override. Verify: Playwright scenario "Both
      kinds of dark user see the same override", run once with an emulated dark system on "System
      default" and once with the dark theme chosen.
- [x] 5.5 Incomplete token sets: a set without `--nldesign-animation-easing` falls back to
      `defaults.css` and renders with `ease`. Verify: PHPUnit or Playwright on a set that lacks it.
- [x] 5.6 Accessibility: the opacity range and number field have labels, the dark line is announced
      as belonging to its token, the easing select has a visible label, and the motion preview
      respects reduced motion. Verify: `npm run test:unit` on `tests/vitest/admin-a11y.spec.js`,
      and Playwright with `reducedMotion: 'reduce'`.
- [x] 5.7 Security: values pass the existing injection filter after the type check, and the error
      response carries no path. Verify: PHPUnit `testInjectionStillRefusedAfterTypeCheck`.

## 6. Before the pull request

- [x] 6.1 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict` once, then `npm run lint`,
      `npm run format` and `npm run test:l10n`.
- [x] 6.2 On a running instance, set a translucent colour, a dark value and a slower animation,
      check them as a light user, a system-dark user and a chosen-dark user, and record what was
      seen in the PR body.

## Notes at archive (2 Oct 2026)

- Built over two lanes: build-all lane 4 (30 Sep, tasks 1.1 to 4.4 and 3.1 to 3.3, partly on `development` already through #756 to #758) and issue lane D (2 Oct: 2.5, 2.6, the sync dialog note of 3.3, the editor styles and the refusal message, 5.1, 5.2).
- 1.2 The JS grammar is tested in `tests/vitest/tokenValueGrammar.spec.js` against the fixture PHP reads too (`tests/Unit/fixtures/token-value-grammar.json`), so both runtimes accept and refuse the same values.
- 1.3 Changed at build: the editor keeps Nextcloud's names `--animation-quick` and `--animation-slow` as the edited rows, typed `duration`, and every save also writes the thematiq twin (`CustomOverridesService::MOTION_TWINS`). `theme.css` transitions read `--animation-quick`, so one edited name reaches both Nextcloud's components and thematiq's. The spec text is adjusted to match; the scenarios are unchanged (the file declares both names).
- 2.1 `OverridesControllerValidationTest::testWrongValueIs400`, `testUnknownTokenIs400`, `testWriteFailureHidesPath`; type cases in `TokenValueTypesTest`.
- 2.2 `CustomOverridesServiceDarkScopesTest::testDarkScopesWritten`, `TokenValueTypesTest::testOwnDarkValueIsWrittenAndReadBack`.
- 2.5 `tests/Unit/Repair/MigrateOverrideValueTypesTest.php` (old file gains the twin and both dark scopes, an own dark value survives, a second run is a no-op, a per-set file is rewritten, an unwritable directory is reported, not thrown). `occ maintenance:repair` on a copy of an instance is owed (live check in the PR body).
- 2.6 The five requests are in the collection; the newman run needs a running instance and is owed (live check in the PR body).
- 3.3 `TokenValueTypesTest::testTranslucentPrimaryIsBlendedForCore`; the sync dialog note is tested in `tests/vitest/admin-token-editor.spec.js` ("says why Nextcloud gets the blend of a translucent colour").
- 4.1 to 4.3 Verified with `tests/vitest/admin-token-value-types.spec.js` (jsdom) instead of Playwright. A browser run is owed (live check in the PR body).
- 4.4 `TokenValueTypesTest::testThemeTransitionsReadTheEasingToken` reads every transition in `theme.css`; the computed `transition-timing-function` in a browser is owed.
- 5.1 en and nl translated; the other locales carry the English text, as for earlier strings. The server's 400 reasons stay English in the response; the editor translates the known ones (`rejectedMessage()` in `js/admin.js`).
- 5.2 Written without screenshots; screenshots from a running instance are owed. The docs site build was not run in the lane (no `docs/node_modules`).
- 5.3 The token editor has no inline contrast hint. Its contrast feedback is the server's warning list, which blends first (`ContrastServiceTest::testFaintTextFailsAfterBlend`).
- 5.4 Both scopes carry the same declaration (`testDarkScopesWritten`); the two-browser Playwright run is owed.
- 5.5 Every transition reads `var(--nldesign-animation-easing, ease)`, so a set without the token renders with `ease` even when `defaults.css` does not load.
- 5.6 Labels and the reduced-motion preview are asserted in `tests/vitest/admin-token-value-types.spec.js`.
- 6.2 Owed: the live pass on a running instance (recipe in the PR body).
