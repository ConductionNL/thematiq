# Tasks: platform-properties

## 1. Tags
- [ ] 1.1 Add `@spec openspec/specs/platform-properties/spec.md#REQ-TPLAT-001` to `LogoLayerService::layer()` in `lib/Service/LogoLayerService.php`
- [ ] 1.2 Add `#REQ-TPLAT-002` and `#REQ-TPLAT-003` to `ThemingService::applyImages()` and `ThemingService::RESETTABLE` in `lib/Service/ThemingService.php`
- [ ] 1.3 Add `#REQ-TPLAT-008` to `Admin::getAuthorizedAppConfig()` in `lib/Settings/Admin.php`
- [ ] 1.4 Add `#REQ-TPLAT-006` to `SettingsController::setSloganSetting()` in `lib/Controller/SettingsController.php`

## 2. Tests that keep the composition true
- [ ] 2.1 New `tests/Unit/Service/LogoLayerServiceTest.php`: with `theming.logoMime` or `theming.logoheaderMime` set, `layer()` does not override core's logo with the set's logo (REQ-TPLAT-001)
- [ ] 2.2 Extend `tests/Unit/Service/ThemingServiceTest.php`: `applyImages()` hands only `logo`, `logoheader`, `favicon` and `background` to core's `ImageManager`, and never `logo_dark` (REQ-TPLAT-002, REQ-TPLAT-003)
- [ ] 2.3 Extend `tests/Unit/Settings/AdminDelegationTest.php`: `Admin` implements `IDelegatedSettings`, and `getAuthorizedAppConfig()` lists `token_set` and does not list a job-internal key such as an upstream freshness ETag (REQ-TPLAT-008)
- [ ] 2.4 New `tests/Unit/AppInfo/InfoXmlTest.php`: `appinfo/info.xml` declares `<id>thematiq</id>`, `<licence>EUPL-1.2</licence>`, `<category>customization</category>` and `<nextcloud min-version="32" max-version="35"/>` (REQ-TPLAT-009)

## 3. Matrix
- [ ] 3.1 When the tasks are done, archive this change and repoint the nine rows in `openspec/parity/capabilities.json` from `built.change` to `built.spec: openspec/specs/platform-properties`
