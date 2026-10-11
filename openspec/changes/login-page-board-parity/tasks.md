# Tasks: The login page of the board

## 1. Spec
- [x] 1.1 Proposal with the mechanism per board element, and the workplace-layout delta.

## 2. Service and script
- [x] 2.1 `LoginCopyService`: the set's `login` block, the `login_copy` override, language maps, validation.
- [x] 2.2 `CssInjectionService`: the login copy on the login page only.
- [x] 2.3 `js/login-copy.js`: intro, alternative logins first with the divider, labels, footer.
- [x] 2.4 `css/workplace-layout.css`: the card in the board's order, the SSO button primary, "Inloggen" outlined.
- [x] 2.5 `token-sets.json`: copy for zuiddrecht, esdoornveen, vaartveld, wilgenboom, warmtepompacademie.
- [x] 2.6 l10n: "or" in every locale, Dutch translated.

## 3. Tests
- [x] 3.1 PHPUnit: `LoginCopyServiceTest`, the login-page-only case in `CssInjectionServiceTest`.
- [x] 3.2 Vitest: `tests/vitest/loginCopy.spec.js`.

## 4. Verify
- [ ] 4.1 Live on :8080: pixel diff against NcLogin with `screens-diff.mjs --no-login`.
