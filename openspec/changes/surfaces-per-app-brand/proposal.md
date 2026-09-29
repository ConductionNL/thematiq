# Per-app brand: an app of the suite gets its own colours and logo

## Why

Some apps in a government workspace have a public identity of their own. An internal social network, a participation platform or a knowledge base often carries its own name, logo and colour, decided centrally. Microsoft 365 is rolling this out for Viva Engage from its brand center (roadmap 568364). openDesk gives each app its own favicon from central values; Liferay gives each connected site its own colours from a design library.

Thematiq applies one house style to the whole instance, per group if needed, and can only exclude an app. The archived change `app-token-set-selection` lets a Conduction app's own picker choose a set for its own users, which is a different thing: the administrator cannot give an app its own brand.

#### Row `sur-per-app-brand` (thematiq matrix, area surfaces)

- Capability: Give a collaboration or social app of the suite its own brand name, large and small logo and theme colour from a central brand center.
- Own rating: no; built.state `none`. Built evidence: thematiq applies one house style instance wide (or per group, sco-per-group) and can only exclude an app (lib/Service/AppThemingService.php disabled_apps); no app can carry its own brand name, logo and colour
- Demand: roadmap at https://www.microsoft.com/microsoft-365/roadmap?id=568364 (Shows branding spreading per app with its own brand center, the fragmentation a single instance-wide theme avoids.)
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `yes`: https://www.microsoft.com/microsoft-365/roadmap?id=568364 (rolling out, 2026-08): Viva Engage admins 'configure a brand name, add large and small logos, and a theme color that is applied consistently for all users', managed from a Brand Center in the Engage admin center
- openDesk theming rated `partial`: opendesk@v1.18.2 helmfile/environments/default/theme.yaml.gotmpl:55-80 gives each app its own favicon (chat, files, groupware, knowledge, notes) from the central theme values, but one productName (:17) and one colour set (:27-47) serve every app; a per-app name or colour needs a per-release customization file that openDesk does not support (customization.yaml.gotmpl:4-24)
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/sites/site-appearance/design-libraries: a library style book gives each connected site its colours and fonts from one central place, and https://learn.liferay.com/w/dxp/security-and-administration/administration/configuring-liferay/virtual-instances/instance-configuration lets site administrators upload their own site logo when allowed; the library holds no brand name or logo, so logo and name stay per-site settings rather than set from the brand center
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, Tokens Studio (Figma plugin and platform) `no`

## What changes

- An administrator maps an app to a token set and, optionally, a large and a small logo, in a new "Brand per app" block on Settings > Administration > Theming.
- On that app's pages the mapped set and logo replace the house style set and logo. Every other page is unchanged.
- The order of authority becomes: an active admin preview, then the app's brand, then the user's group mapping, then the instance default.
- The app's name stays Nextcloud's, because the app menu and page titles are core's.
- Mappings travel in the configuration bundle; logos as metadata.

## Capabilities

### New capabilities

- None.

### Modified capabilities

- `per-app-theming`: brand per app next to the exclusion list.
- `per-group-theming`: the resolution order gains the app brand step.

## Impact

- `lib/Service/AppThemingService.php` (exclusion list at `:46`, `resolveAppIdFromPath()` at `:182`): stores `app_brands` next to `disabled_apps`.
- `lib/Listener/ThemeInjectionListener.php` resolves the app id already (`:192-194`) and passes it to `CssInjectionService::inject()` (`:247`), which uses it where it resolves the token set (`:261`) and the logo layer (`--nldesign-logo-url`, `:725-735`).
- `templates/settings/admin.php` and `js/admin.js`: the Brand per app block under Theming per app.
- Logos in app data `app-brands/`, validated like the converter's logo asset.

## Rows

- `sur-per-app-brand` (thematiq matrix).
