## 1. Store and locator

- [x] 1.1 Add `RuntimeFileStore` over `IAppData` with the closed name patterns from design.md and a revision per name, and verify PHPUnit cases for write, read, revision bump and a refused `../` name
- [x] 1.2 Add `TokenSetFileLocator` returning shipped content from the release and uploaded content from the store, and verify a shipped id cannot be served from the store

## 2. Writers

- [x] 2.1 Move `CustomOverridesService` and `CustomCssService` onto the store, and verify their existing tests pass with a store double built with `onlyMethods`
- [x] 2.2 Move `CustomTokenSetService` uploads (stylesheet and logo) onto the store, and verify an upload leaves the app directory unchanged
- [x] 2.3 Move `BrandingCaptureService` logos and backgrounds onto the store, and verify the same
- [x] 2.4 Restrict `DarkPaletteService` runtime generation to uploaded sets, writing into the store, and verify a forced run changes no shipped dark file

## 3. Readers

- [x] 3.1 Switch every reader of a set's file to the locator, and verify a PHPUnit guard that finds no `getAppPath(` next to a runtime path outside the locator and the repair step

## 4. Serving and injection

- [x] 4.1 Add the two public routes and `RuntimeFileController` with immutable caching and ETag, and verify PHPUnit cases for a hit, a miss and a traversal attempt
- [x] 4.2 Add the `runtime` layer kind to `CssInjectionService`, linking the route with `?v=<revision>`, and verify the manifest test lists the overrides layer as `runtime`
- [x] 4.3 Make `logoUrlLayer()` ask the locator, and verify an uploaded logo resolves to the route URL

## 5. Migration

- [x] 5.1 Add `MoveRuntimeFilesToAppData` on install and post-migration, and verify both scenarios of "Existing runtime files are moved on upgrade" in PHPUnit
- [x] 5.2 Add the vitest freshness check for shipped dark variants, and verify it fails on a stale fixture

## 6. Proof on a live instance

- [ ] 6.1 Hash the app directory on 8080, then upload a set with a logo and background, apply it, save overrides and custom CSS, and verify the hashes and the file list are unchanged
- [ ] 6.2 Verify in a browser that the uploaded logo shows in the header and loads from the runtime route, on a workspace page and on the login page
- [ ] 6.3 Copy a signed release into a test path, run `occ integrity:check-app` before and after the same admin session, and verify both report no difference
  (A signed copy needs Conduction's signing key, which this session does not have. The PHPUnit test `testACustomHouseStyleLeavesTheAppDirectoryUnchanged` hashes a copy of the app directory around every writer, which is the property the integrity check compares; the signed check itself stays for the release.)

## 7. Documentation and verification

- [x] 7.1 Document the storage move and the rollback caveat in the admin docs and `CHANGELOG.md`, and verify the docs site builds
- [ ] 7.2 Run `COMPOSER_PROCESS_TIMEOUT=0 composer check:strict`, PHPUnit in the installed container, vitest with two workers, `npm run format` and `npm run test:l10n` once before push, and record the results in the PR body

Reminders, not tasks:
- ADR-005: the public routes serve only names the store accepts; no listing, no path from the request reaches the filesystem.
- ADR-016: both routes are registered in `appinfo/routes.php` with their auth attributes.
- Inherited findings on untouched lines go in one sentence in the PR body.
