# Spec delta: Theming Sync (nlds-theme-converter)

The sync itself is unchanged: the admin still confirms a dialog, the same validation still runs in
the same order, and the same two services are still the only things touched. One requirement is
corrected, because it described a call that silently did nothing.

`ImageManager::updateImage()` stores the file and RETURNS the mime type it detected; the
`{key}Mime` app value is the caller's job, and it is what `ThemingDefaults::getLogo()` reads to
decide whether a custom image exists at all. `applyImages()` dropped the return value, so a logo
sync looked entirely successful — the endpoint answered `{"status":"ok","updated":["logo"]}`,
`ImageManager::hasImage('logo')` said true, the file was on disk — while every page, e-mail and
login screen kept rendering the stock Nextcloud logo. Core's own
`ThemingController::uploadImage()` pairs the two calls; this one now does too.

## MODIFIED Requirements

### Requirement: Apply Images to Nextcloud Theming
The app MUST apply validated image paths to Nextcloud's `ImageManager` service using full
filesystem paths, and MUST persist the mime type `ImageManager::updateImage()` returns, because
Nextcloud reads the image through the `{key}Mime` app value rather than through the file's presence.

#### Scenario: Logo image applied
- GIVEN a valid request with `logo: "img/logos/amsterdam.svg"`
- AND the file exists at `{appPath}/img/logos/amsterdam.svg`
- WHEN `applyImages()` is called
- THEN `ImageManager::updateImage('logo', '{appPath}/img/logos/amsterdam.svg')` MUST be called with
  the full absolute path
- AND `ThemingDefaults::set('logoMime', …)` MUST be called with the mime type that call returned
- AND `"logo"` MUST appear in the list of updated fields

#### Scenario: Background image applied
- GIVEN a valid request with `background: "img/backgrounds/default.jpg"`
- AND the file exists
- WHEN `applyImages()` is called
- THEN `ImageManager::updateImage('background', '{fullPath}')` MUST be called
- AND `ThemingDefaults::set('backgroundMime', …)` MUST be called with the returned mime type
- AND `"background"` MUST appear in the list of updated fields

#### Scenario: The synced logo is the one Nextcloud serves
- GIVEN a token set whose `theming.logo` names a file in the app's `img/logos/`
- WHEN the theming sync is confirmed
- THEN `GET /apps/theming/image/logo` MUST return that file's bytes and its mime type
- AND the generated theming stylesheet's `--image-logo` MUST point at that route, not at
  `core/img/logo/logo.png`

#### Scenario: Empty image path ignored
- GIVEN a request where `logo` is empty or not set
- WHEN `applyImages()` is called
- THEN `ImageManager::updateImage()` MUST NOT be called for `logo`
- AND no `{key}Mime` app value MUST be written

#### Scenario: App path resolved via IAppManager
- GIVEN images need to be applied
- WHEN the full path is constructed
- THEN `IAppManager::getAppPath('thematiq')` MUST be used to resolve the base directory
- AND the relative path MUST be appended to get the full filesystem path
