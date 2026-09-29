# NL-Government & French-Government Icons, and Logos

This directory contains SVG icons materialized from the EUPL-compatible NL-government
icon packs bundled in `@conduction/nextcloud-vue` (`src/icons/{rvo,openGemeenten,denHaag}.js`),
the French-government DSFR pack from `@gouvfr/dsfr` (`dist/icons/**/*.svg`), plus the
organisation logos in `img/logos/`.

**The proprietary City-of-Amsterdam icon set (`@amsterdam/design-system-assets`) is NOT
bundled.** Its `LICENSE.md` marks the set proprietary to the City of Amsterdam ("The
open-source licence does NOT apply to files in this directory"), restricted to contexts
where Amsterdam is the main communicator — redistributing it to arbitrary Dutch-government
Nextcloud instances is exactly the use its notice forbids. It was removed from this app;
see CHANGELOG.md for the full list of removed filenames and their replacements.

## Icons

### rvo (1163 icons)

Upstream: RVO / ROOS (Rijksdienst voor Ondernemend Nederland)
Licence: **CC0-1.0**
Available in: `img/icons/rvo/`

- rvo/rvo-aangifte-ondernemers
- rvo/rvo-aanrecht-met-kraan-en-koffiekan
- rvo/rvo-aar
- rvo/rvo-aar-met-bladeren
- rvo/rvo-accijns
- rvo/rvo-accu
- rvo/rvo-actieve-gevel
- rvo/rvo-activiteit
- rvo/rvo-adl-woning
- rvo/rvo-advocaat
- rvo/rvo-afbrokkelend-schild-met-capsule
- rvo/rvo-afhaaleten
- rvo/rvo-afhaalpunt
- rvo/rvo-afrit
- rvo/rvo-afsprakenstelsel
- rvo/rvo-afstand-houden
- rvo/rvo-afstand-houden-armen
- rvo/rvo-afvalcontainer-plastic
- rvo/rvo-agile-werken
- rvo/rvo-algemeen-alarm
... and 1143 more

### open-gemeenten (256 icons)

Upstream: OpenGemeenten Iconenset ("Line" style)
Licence: **CC0-1.0**
Available in: `img/icons/open-gemeenten/`

- open-gemeenten/og-aanmelden
- open-gemeenten/og-actueel
- open-gemeenten/og-afval
- open-gemeenten/og-afvalgft
- open-gemeenten/og-afvalglascontainer
- open-gemeenten/og-afvalgroenbak
- open-gemeenten/og-afvalophalen
- open-gemeenten/og-afvalpmd
- open-gemeenten/og-afvalscheiden
- open-gemeenten/og-afvalbakafvalzak
- open-gemeenten/og-afvalbakpmd
- open-gemeenten/og-afvalcontainer
- open-gemeenten/og-afvalcontainerafvalzak
- open-gemeenten/og-afvalcontainerpas
- open-gemeenten/og-afvalkalender
- open-gemeenten/og-afvalzak
- open-gemeenten/og-agenda
- open-gemeenten/og-airborne
- open-gemeenten/og-armoede
- open-gemeenten/og-asbestverwijderen
... and 236 more

### den-haag (69 icons)

Upstream: Gemeente Den Haag icon set
Licence: **EUPL-1.2**
Available in: `img/icons/den-haag/`

- den-haag/dh-arrows-arrow-left
- den-haag/dh-arrows-arrow-right
- den-haag/dh-arrows-chevron-down
- den-haag/dh-arrows-chevron-left
- den-haag/dh-arrows-chevron-right
- den-haag/dh-arrows-chevron-up
- den-haag/dh-communication-call
- den-haag/dh-communication-email
- den-haag/dh-communication-message
- den-haag/dh-functional-checked
- den-haag/dh-functional-close
- den-haag/dh-functional-download
- den-haag/dh-functional-edit
- den-haag/dh-functional-external-link
- den-haag/dh-functional-favorite
- den-haag/dh-functional-folder
- den-haag/dh-functional-grid
- den-haag/dh-functional-hamburger
- den-haag/dh-functional-hide
- den-haag/dh-functional-list
... and 49 more

### dsfr (1038 icons)

Upstream: Système de Design de l'État (DSFR) — @gouvfr/dsfr
Licence: **Etalab-2.0**
Available in: `img/icons/dsfr/`

- dsfr/account-circle-fill
- dsfr/account-circle-line
- dsfr/account-pin-circle-fill
- dsfr/account-pin-circle-line
- dsfr/add-circle-fill
- dsfr/add-circle-line
- dsfr/add-line
- dsfr/admin-fill
- dsfr/admin-line
- dsfr/airplay-fill
- dsfr/airplay-line
- dsfr/alarm-warning-fill
- dsfr/alarm-warning-line
- dsfr/alert-fill
- dsfr/alert-line
- dsfr/align-center
- dsfr/align-justify
- dsfr/align-left
- dsfr/align-right
- dsfr/anchor-fill
... and 1018 more

DSFR filenames (`dsfr/{basename}.svg`) are the source SVG's basename with its category
prefix dropped (e.g. `dist/icons/arrows/arrow-right-line.svg` -> `dsfr/arrow-right-line.svg`):
DSFR icon names are unique across the whole set (referenced globally in DSFR CSS as
`.fr-icon-<name>`), so this flat, category-free layout is collision-free — the build fails
on any duplicate basename.

## Icon-pack resolution (theme-switchable iconography)

The icon pack an app should serve travels with the active **design system**, not a fixed
set: `design-systems.json` carries an optional `icon_pack` field per design system
(`nldesign` -> `["rvo", "open-gemeenten", "den-haag"]`, `lasuite` -> `["dsfr"]`; a design
system without the field serves no pack — Nextcloud stock icons apply). Resolution is
`active token set -> its design_system -> that design system's icon_pack`, with an
appconfig `icon_pack` admin override taking precedence when it names a real pack
directory. Consumers who want a **fixed** pack keep using
`imagePath('nldesign', 'icons/{set}/{key}.svg')` exactly as before (unchanged, non-breaking).
Consumers who want the **active theme's** pack should resolve it through
`DesignSystemService::resolveActiveIconPacks()` / `resolveIconPath()`, or read the
resolved list from the public capability (`/ocs/v2.php/cloud/capabilities` ->
`capabilities.nldesign.iconPacks`) — see `openspec/specs/icon-packs/spec.md`.

**Honest limitation:** this only switches nldesign's own bundled icon assets (the ones
served through `imagePath` and the capability above). It does NOT force-replace
Nextcloud core's built-in icon set (navigation, files, the Material-style core icons)
beyond what the active theme's CSS already restyles.

## Licence attribution

Canonical licence record for the NL packs: `@conduction/nextcloud-vue`'s
`src/icons/ATTRIBUTION.md` (https://github.com/ConductionNL/nextcloud-vue/blob/main/src/icons/ATTRIBUTION.md).
The DSFR pack is licensed under the Etalab Open Licence 2.0
(https://github.com/etalab/licence-ouverte/blob/master/LO.md), redistributed via
`@gouvfr/dsfr` — Système de Design de l'État (https://www.systeme-de-design.gouv.fr/).
This materialization is **icons only**: the Marianne typeface files shipped in the same
`@gouvfr/dsfr` package are FR-state-restricted and are never bundled by this script.

| Set | Upstream | Licence |
| --- | --- | --- |
| `rvo` | RVO / ROOS (Rijksdienst voor Ondernemend Nederland) | **CC0-1.0** |
| `open-gemeenten` | OpenGemeenten Iconenset ("Line" style) | **CC0-1.0** |
| `den-haag` | Gemeente Den Haag icon set | **EUPL-1.2** |
| `dsfr` | Système de Design de l'État (DSFR) — @gouvfr/dsfr | **Etalab-2.0** |

## Legacy Amsterdam filename aliases (one-release deprecation)

For exactly **one release**, the legacy Amsterdam Design System filenames listed below
resolve to a top-level `img/icons/{Name}.svg` file — a byte-identical copy of the mapped
replacement artwork below, never Amsterdam artwork. **These aliases are removed in the
next minor release** (`scripts/icon-aliases.json` emptied, the build stops emitting
top-level files). Any legacy name NOT in this table returns HTTP 404 today; see
CHANGELOG.md for the full list of names removed without a replacement.

| Legacy path (deprecated) | Replacement artwork |
| --- | --- |
| `Search.svg` | `den-haag/dh-functional-search.svg` |
| `Star.svg` | `den-haag/dh-objects-star.svg` |
| `StarFill.svg` | `rvo/rvo-ster.svg` |
| `House.svg` | `den-haag/dh-objects-house.svg` |
| `HouseFill.svg` | `rvo/rvo-home.svg` |
| `Calendar.svg` | `den-haag/dh-objects-calendar.svg` |
| `CalendarFill.svg` | `rvo/rvo-kalender.svg` |
| `Mail.svg` | `den-haag/dh-communication-email.svg` |
| `MailFill.svg` | `rvo/rvo-mail.svg` |
| `Phone.svg` | `den-haag/dh-communication-call.svg` |
| `PhoneFill.svg` | `rvo/rvo-telefoon.svg` |
| `Person.svg` | `den-haag/dh-objects-user.svg` |
| `PersonFill.svg` | `rvo/rvo-user.svg` |
| `Persons.svg` | `rvo/rvo-groep-3-personen.svg` |
| `UserAccount.svg` | `den-haag/dh-objects-user.svg` |
| `UserAccountFill.svg` | `rvo/rvo-user.svg` |
| `Settings.svg` | `den-haag/dh-informational-settings.svg` |
| `SettingsFill.svg` | `den-haag/dh-informational-settings.svg` |
| `Cogwheel.svg` | `den-haag/dh-informational-settings.svg` |
| `CogwheelFill.svg` | `den-haag/dh-informational-settings.svg` |
| `Document.svg` | `den-haag/dh-objects-document.svg` |
| `DocumentFill.svg` | `rvo/rvo-document-blanco.svg` |
| `Download.svg` | `den-haag/dh-functional-download.svg` |
| `Upload.svg` | `rvo/rvo-upload.svg` |
| `ArrowBackward.svg` | `den-haag/dh-arrows-arrow-left.svg` |
| `ArrowForward.svg` | `den-haag/dh-arrows-arrow-right.svg` |
| `ArrowUp.svg` | `rvo/rvo-pijl-omhoog.svg` |
| `ArrowDown.svg` | `rvo/rvo-pijl-omlaag.svg` |
| `ChevronBackward.svg` | `den-haag/dh-arrows-chevron-left.svg` |
| `ChevronForward.svg` | `den-haag/dh-arrows-chevron-right.svg` |
| `ChevronUp.svg` | `den-haag/dh-arrows-chevron-up.svg` |
| `ChevronDown.svg` | `den-haag/dh-arrows-chevron-down.svg` |
| `Close.svg` | `den-haag/dh-functional-close.svg` |
| `Menu.svg` | `den-haag/dh-functional-hamburger.svg` |
| `Map.svg` | `den-haag/dh-objects-map.svg` |
| `MapMarker.svg` | `rvo/rvo-locatiemarker.svg` |
| `MapMarkerFill.svg` | `rvo/rvo-locatiemarker.svg` |
| `Print.svg` | `rvo/rvo-printer.svg` |
| `Delete.svg` | `den-haag/dh-functional-trash.svg` |
| `TrashBin.svg` | `den-haag/dh-functional-trash.svg` |
| `Pencil.svg` | `den-haag/dh-functional-edit.svg` |
| `Pen.svg` | `den-haag/dh-functional-edit.svg` |
| `Info.svg` | `den-haag/dh-informational-circle-information.svg` |
| `InfoFill.svg` | `den-haag/dh-informational-circle-information.svg` |
| `QuestionMarkCircle.svg` | `den-haag/dh-informational-circle-help.svg` |
| `QuestionMarkCircleFill.svg` | `den-haag/dh-informational-circle-help.svg` |
| `CheckMark.svg` | `den-haag/dh-functional-checked.svg` |
| `CheckMarkCircle.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `CheckMarkCircleFill.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `Warning.svg` | `den-haag/dh-informational-alert-triangle.svg` |
| `WarningFill.svg` | `den-haag/dh-informational-alert-triangle-filled.svg` |
| `Error.svg` | `den-haag/dh-informational-circle-warning.svg` |
| `ErrorFill.svg` | `den-haag/dh-informational-circle-warning.svg` |
| `Success.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `SuccessFill.svg` | `den-haag/dh-informational-checkcircle.svg` |
| `EyeOpen.svg` | `den-haag/dh-functional-show.svg` |
| `EyeClosed.svg` | `den-haag/dh-functional-hide.svg` |
| `Folder.svg` | `den-haag/dh-functional-folder.svg` |
| `LinkExternal.svg` | `den-haag/dh-functional-external-link.svg` |
| `Heart.svg` | `den-haag/dh-functional-favorite.svg` |
| `HeartFill.svg` | `den-haag/dh-functional-favorite.svg` |
| `Grid.svg` | `den-haag/dh-functional-grid.svg` |
| `List.svg` | `den-haag/dh-functional-list.svg` |
| `LogOut.svg` | `den-haag/dh-functional-log-out.svg` |
| `Share.svg` | `den-haag/dh-functional-share.svg` |
| `Building.svg` | `den-haag/dh-objects-building.svg` |
| `Car.svg` | `den-haag/dh-objects-car.svg` |
| `Bed.svg` | `den-haag/dh-objects-bed.svg` |
| `Euro.svg` | `den-haag/dh-objects-euro.svg` |
| `Image.svg` | `den-haag/dh-objects-image.svg` |
| `Wallet.svg` | `den-haag/dh-objects-wallet.svg` |
| `Clipboard.svg` | `den-haag/dh-objects-clipboard.svg` |
| `Facebook.svg` | `den-haag/dh-social-facebook.svg` |
| `Instagram.svg` | `den-haag/dh-social-instagram.svg` |
| `LinkedIn.svg` | `den-haag/dh-social-linkedin.svg` |
| `Whatsapp.svg` | `den-haag/dh-social-whatsapp.svg` |
| `X.svg` | `den-haag/dh-social-twitter-x.svg` |

## Usage in Nextcloud

```php
// In your template or controller — a FIXED pack, regardless of the active theme
<img src="<?php p(\OC::$server->getURLGenerator()->imagePath('nldesign', 'icons/rvo/rvo-zoek.svg')); ?>" alt="Search">
<img src="<?php p(\OC::$server->getURLGenerator()->imagePath('nldesign', 'icons/dsfr/arrow-right-line.svg')); ?>" alt="Next">
```

Vue-based consumers SHOULD prefer importing the NL packs from `@conduction/nextcloud-vue`
directly (e.g. `CnIconBrowser` `url-icons`); the `imagePath` contract above exists for
PHP/template and other non-Vue consumers.

These URLs only resolve while the nldesign app is enabled. Consumers must ship a fallback
icon or declare a dependency on `nldesign`.

## Logos (26 total)

Available in: `img/logos/`. Static, checked-in huisstijl assets tied to token sets
(`token-sets.json` `theming.logo` entries) — organisation marks displayed as that
organisation's own identity on that organisation's own instance. **Not build output**:
`scripts/build-icons.js` never creates, modifies, or deletes anything under `img/logos/`.

- amsterdam
- denhaag
- dinkelland
- drechterland
- epe
- epe-dark
- frankendesk
- ggd-amsterdam
- hoorn
- leiden
- museum_weesp
- nijmegen
- noaberkracht
- noordwijk
- opencatalogi
- provincie-zuid-holland
- rijkshuisstijl
- rotterdam
- stadsarchief
- stadsbank-van-lening
- tilburg
- tubbergen
- utrecht
- vga-verzekeringen
- vng
- xxllnc

## Naming stability (public API)

Icon and logo filenames are a public API consumed by other apps. Within an installed
source pack version, renaming or removing a bundled icon or logo is a **breaking change**:
record it in the changelog (old + new name) and regenerate this file in the same change so
the inventory regression test (`tests/Unit/IconAssetsTest.php`) keeps passing. Upgrading a
source dependency that can change pack contents — `@conduction/nextcloud-vue` (the
`rvo`/`open-gemeenten`/`den-haag` packs) or `@gouvfr/dsfr` (the `dsfr` pack) — MUST be an
explicit, reviewed change whose diff of added/removed keys is listed in the changelog —
never a silent regenerate. Removing the legacy Amsterdam aliases at the end of the
deprecation window is a planned, pre-announced break that references the release that
announced it.
