# Delegated group house style: some groups choose, the rest stay locked

## Why

On a shared instance, such as a regional cooperation of municipalities, the brand owner wants two things at once. Most groups must keep the organisation's house style and must not drift. A few, such as a municipality in the cooperation or a department with its own public identity, should pick their own style without asking the central administrator each time.

Thematiq does the first half: only an administrator changes the house style, so every group is locked. It cannot do the second. Microsoft 365 shipped exactly this as site branding governance (roadmap 526794): enforce the enterprise theme, apply themes to chosen sites, disable custom branding on specific sites, audit the changes.

#### Row `gov-lock-per-space` (thematiq matrix, area governance)

- Capability: Lock the organisation theme on selected sites or spaces, so their owners cannot change the branding, while other spaces keep their freedom.
- Own rating: partial; built.state `built`. Built evidence: nobody but an administrator can change the house style anywhere (gov-admin-only; SettingsController setters are admin routes), so every space is locked; the reverse, letting chosen spaces keep their own freedom, is limited to excluding whole apps (lib/Service/AppThemingService.php disabled_apps list)
- Demand: roadmap at https://www.microsoft.com/microsoft-365/roadmap?id=526794 (A government brand owner needs to stop departmental sites drifting from the Rijkshuisstijl without locking down the whole instance.)
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `yes`: https://www.microsoft.com/microsoft-365/roadmap?id=526794 (launched 2026-02): admins 'enforce consistent branding, apply enterprise themes to individual sites, disable custom branding on specific sites, and audit branding changes'; https://learn.microsoft.com/en-us/powershell/module/microsoft.online.sharepoint.powershell/set-sposite: -DisableSiteBranding
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/sites/site-appearance/design-libraries: connecting a site needs update permission on the library, 'a site administrator cannot attach or detach their own site', and a published library style book 'a site can neither override'; but a connected site 'can still define its own style books', and https://learn.liferay.com/w/dxp/security-and-administration/administration/configuring-liferay/virtual-instances/instance-configuration 'Allow site administrators to use their own logo?' is one instance-wide switch, not per site
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, openDesk theming `no`, Tokens Studio (Figma plugin and platform) `no`

## What changes

- An administrator can mark a group mapping as delegated and give it a list of allowed token sets. Groups without the mark stay locked, as today.
- A subadmin of a delegated group chooses that group's set from the allowed list in a new personal settings section, "House style of my groups".
- Every delegated choice is validated against the allowed list, applied through the existing group mapping, and written to the audit log with the subadmin as actor.
- The administrator can lock a delegated group again at any time; the group then keeps the set it has until the administrator changes it.

## Capabilities

### New capabilities

- None.

### Modified capabilities

- `per-group-theming`: delegated entries, the subadmin section and its endpoints.

## Impact

- `lib/Service/GroupThemingService.php`: entries gain `delegated` and `allowedTokenSets` (`setMapping()` at `:172`, entry validation at `:189-200`); a new `setDelegatedTokenSet()`.
- `lib/Controller/SettingsController.php` (`:907`, `:934`): admin read and write carry the new fields. A new controller for the subadmin endpoints, `#[NoAdminRequired]` with a per-group subadmin check through `OCP\Group\ISubAdmin`.
- New `lib/Settings/Personal.php` and `templates/settings/personal.php`, registered in `appinfo/info.xml` next to the admin section (`:241`), shown only to subadmins of a delegated group.
- `templates/settings/admin.php` (`:338-360`) and `js/admin.js`: a delegate toggle and an allowed sets picker per mapping row.
- Audit: `token_set_changed` entries with the group in context.

## Rows

- `gov-lock-per-space` (thematiq matrix): the missing half, letting chosen groups keep their own choice while the others stay locked.
