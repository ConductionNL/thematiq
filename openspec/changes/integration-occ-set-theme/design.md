# Design: set the active theme from the command line

## Where it fits (development b4e7568)

- `lib/Controller/SettingsController.php:226-243` `setTokenSet()`: checks `TokenSetService::isValidTokenSet()` (`lib/Service/TokenSetService.php:504`), writes app config `token_set`, logs `token_set_changed` with old and new.
- `lib/Service/TokenSetService.php:226` `getAvailableTokenSets()` merges shipped and custom sets.
- `lib/Service/GroupThemingService.php:172` `setMapping()` holds the group mappings shown by `get`.
- `lib/Service/ThemingService.php:189` `applyColors()` and `:233` `applyImages()` are what the theming-sync dialog calls to push a set's theming metadata into Nextcloud core theming.
- `lib/Service/ConfigBundleService.php:900` sets `token_set` as one field of a full bundle import, the only command-line path today (`lib/Command/ConfigImport.php`).
- Commands are registered in `appinfo/info.xml:235-238` under the `nldesign:` prefix; the open change `rename-nldesign-to-themiq` will rename the prefix for all commands together.

## Decisions

### 1. One write path

`TokenSetService::activate(string $id, string $actor)` takes over the body of `setTokenSet()`: validate, write, audit. The controller and the command both call it. An operator then gets exactly the validation and audit an administrator gets.

### 2. Core sync is explicit

The web path shows the theming-sync dialog. A script cannot answer a dialog, so `--sync-core` applies the set's theming metadata (primary colour, background, logo) through `ThemingService`, and without the flag core theming stays as it is. The output says which of the two happened.

### 3. The prefix follows the other commands

`nldesign:theme:*` matches `nldesign:config:*` today. The rename change moves all commands at once; this change does not introduce a second prefix.

## Risks

- A script that switches the set on every run floods the audit log. `set` to the set that is already active is a no-op that exits 0 and writes no entry.

## Out of scope

- Setting group mappings from the command line. `nldesign:config:import` covers them as part of a bundle.
