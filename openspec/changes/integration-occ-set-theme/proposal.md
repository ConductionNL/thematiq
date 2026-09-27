# Set the active theme from the command line

## Why

An operator who wants to switch the house style from a script has to build a whole configuration bundle with the desired token set in it and import that. The bundle import is meant for promoting a complete configuration between environments, not for one field. Microsoft 365 (Graph and PnP PowerShell) and openDesk (Helm values) set the theme with one command; Nextcloud Theming and Liferay do it partly.

#### Row `int-cli-set-theme` (thematiq matrix, area integration)

- Capability: Set the active theme from the command line.
- Own rating: partial; built.state `built`. Built evidence: lib/Service/ConfigBundleService.php:900 import() does $this->config->setAppValue(Application::APP_ID, 'token_set', $config['tokenSet']), so occ nldesign:config:import (lib/Command/ConfigImport.php) does set the active token set as one field of a full bundle import; there is no single-purpose 'occ nldesign:set-theme <name>' command
- Nextcloud Theming (built-in app) rated `partial`: nextcloud/server@v35.0.1 apps/theming/lib/Command/UpdateConfig.php:22-24 sets name, colours, URLs and images from the CLI and config/config.sample.php:2471-2476 enforce_theme picks a built-in theme instance-wide via occ config:system:set; there is no house-style id to set
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `yes`: https://learn.microsoft.com/en-us/powershell/module/microsoft.graph.identity.directorymanagement/update-mgorganizationbranding : Update-MgOrganizationBranding (Microsoft Graph PowerShell) and PnP PowerShell cmdlets let you set branding/site theme values from the command line
- openDesk theming rated `yes`: opendesk@v1.18.2 docs/getting-started.md:455 helmfile apply sets the theme from values
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/development/customizing-liferays-look-and-feel/using-a-theme-css-client-extension : theme CSS client extensions deploy from the command line (gradlew deploy, lcp deploy), and style books can be created over the headless API (https://liferay.atlassian.net/browse/LPD-101910), but activating a theme for a site from a CLI is not documented
- Rated no or unknown: Tokens Studio (Figma plugin and platform) `no`

## What changes

- `occ nldesign:theme:list` lists the available token sets with id, name and source (shipped or custom).
- `occ nldesign:theme:get` prints the active token set and the group mappings.
- `occ nldesign:theme:set <token-set> [--sync-core] [--dry-run]` validates the id like the settings dropdown, switches the instance-wide set, optionally applies the set's theming metadata to Nextcloud core theming, and writes an audit entry with actor `cli`.
- Exit codes are fit for scripts: 0 on success, non-zero with a message on an unknown set.

## Capabilities

### New capabilities

- `theme-cli`: the three commands.

### Modified capabilities

- None.

## Impact

- New `lib/Command/ThemeList.php`, `lib/Command/ThemeGet.php`, `lib/Command/ThemeSet.php`, registered in `appinfo/info.xml` (`:235-238`).
- The token set write moves from `SettingsController::setTokenSet()` (`:226-243`) into a service method both use, so the command cannot drift from the web path. The change `apply-scheduled-theme-switch` needs the same method; whichever lands first adds it.
- `lib/Service/ThemingService.php` `applyColors()` (`:189`) and `applyImages()` (`:233`) for `--sync-core`.

## Rows

- `int-cli-set-theme` (thematiq matrix): the missing half, a single-purpose command.
