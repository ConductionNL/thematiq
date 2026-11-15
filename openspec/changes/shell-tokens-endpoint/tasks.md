# Tasks: shell-tokens-endpoint

## 1. Map

- [x] 1.1 `lib/Service/ShellRoleMapper.php`: one table of sources per role, the derived roles, the
      documented defaults. Pure.
- [x] 1.2 `tests/Unit/Service/ShellRoleMapperTest.php`: the defaults, Gemeente Zuiddrecht from its
      shipped file, every shipped set gets eighteen usable values, an administrator's value wins,
      a lighter hover is not the deep colour, unusable values are passed over.

## 2. Assemble

- [x] 2.1 `lib/Service/ShellTokenService.php`: the active set (the stock set when the configured one
      is gone), its declarations through `ShippedTokenSetAuditService::resolveDeclarations()`, the
      stock set's live values, the administrator's values, the logo, the revision.
- [x] 2.2 `LogoLayerService::shippedLogo()` is public, so the document names the file the header draws.
- [x] 2.3 `tests/Unit/Service/ShellTokenServiceTest.php`: the shape, no empty token, a removed set,
      the stock set's live values, the revision moves with every value, the logo order, the login
      background is not the page colour.

## 3. Serve

- [x] 3.1 `lib/Controller/ShellTokenController.php`: `#[PublicPage]`, `#[NoCSRFRequired]`,
      `#[AnonRateLimit(limit: 120, period: 60)]`, `ETag`, `304` on `If-None-Match`.
- [x] 3.2 `appinfo/routes.php`: `GET /api/shell-tokens`.
- [x] 3.3 `tests/Unit/Controller/ShellTokenControllerTest.php`: the route, the three attributes, the
      document and its `ETag`, `304` for a quoted, weak, listed and `*` validator, `200` for a stale one.
- [x] 3.4 `tests/integration/thematiq.postman_collection.json`: an anonymous read answers 200 with
      eighteen roles, and the revision sent back answers 304. Written, not run: no instance on the
      build machine could be changed.

## 4. Document

- [x] 4.1 `docs/features/shell-tokens-api.md`: the endpoint, the fields, how to read it.

## 5. Checks

- [x] 5.1 `php -l`, phpcs, php-cs-fixer, phpmd, psalm and phpstan on the touched files; the three
      new test classes.
- [x] 5.2 `composer check:strict`, once, before the push.
- [ ] 5.3 One call against a running instance once the change is on it: 200 without a session, 304
      with the revision.
