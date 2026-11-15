# Tasks: The not-found page of the board

## 1. Spec
- [x] 1.1 Proposal and the workplace-layout delta.

## 2. Service, script and styles
- [x] 2.1 `CopyValues`: the shared value rules; `LoginCopyService` uses them.
- [x] 2.2 `NotFoundCopyService`: the set's `not_found` block, the `not_found_copy` override, links.
- [x] 2.3 `ThemeInjectionListener`: the copy on the core `404` template on a themed context.
- [x] 2.4 `js/not-found.js` and `css/workplace-layout.css`; the empty icon block hidden.
- [x] 2.5 `token-sets.json`: zuiddrecht ships the board's copy.

## 3. Tests
- [x] 3.1 PHPUnit: `NotFoundCopyServiceTest`, the 404 cases in `ThemeInjectionListenerTest`.
- [x] 3.2 Vitest: `tests/vitest/notFound.spec.js`.

## 4. Verify
- [x] 4.1 Live on :8080, logged out and logged in, against the NietGevonden board.
