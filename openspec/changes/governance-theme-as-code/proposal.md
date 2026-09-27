# Theme as code: a branding package in Git, applied from deployment configuration

## Why

Operations teams that run Nextcloud with Helm or Ansible version everything in Git and review it through pull requests. The house style is the exception. It lives in the database and moves between environments as a JSON bundle that an administrator downloads and uploads by hand, and that bundle leaves the fonts behind: the administrator re-uploads every font on each environment (`lib/Service/ConfigBundleService.php:50-57`).

Three rows ask for the same thing from three sides. openDesk and Tokens Studio rate yes on declaring the theme in deployment configuration and on keeping the token source in Git; Nextcloud Theming, Microsoft 365 and Liferay rate partial. Liferay's roadmap asks for one branding package that applies itself.

#### Row `gov-config-as-code` (thematiq matrix, area governance)

- Capability: Declare the theme in deployment configuration (for example Helm values) so it is versioned with the infrastructure.
- Own rating: no; built.state `none`. Built evidence: grep -rli 'helm|values.yaml|config-as-code|infrastructure as code' across the repo (docs, openspec, appinfo) found only two openspec files, and reading them showed the 'helm' hit was a substring match inside the unrelated word 'overwhelm' (openspec/specs/lasuite-parity/spec.md:172); no Helm chart, values file, Kubernetes manifest or IaC integration exists anywhere in this repo
- Nextcloud Theming (built-in app) rated `partial`: nextcloud/server@v35.0.1 config/config.sample.php:2463-2484 keys theme, enforce_theme and theming.standalone_window.enabled live in config.php, and occ theming:config (apps/theming/lib/Command/UpdateConfig.php) can be run from Helm or Ansible hooks, but colours, name and images live in the database, not declarative config
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `partial`: https://microsoft365dsc.com/resources/sharepoint/SPOTheme/: SPOTheme is a declarative Microsoft365DSC resource (Name, IsInverted, Palette) that can be versioned with infrastructure; Entra sign-in branding and the M365 org theme have Graph APIs but no declarative resource in that list
- openDesk theming rated `yes`: opendesk@v1.18.2 helmfile/environments/default/theme.yaml.gotmpl:13-154 and docs/theming.md:18: the theme is Helm/helmfile values
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/development/customizing-liferays-look-and-feel/using-a-theme-css-client-extension : the theme CSS and token definition are declared in client-extension.yaml and deployed with Gradle or lcp deploy, but assigning it to pages and all style book values are UI or database state; https://learn.liferay.com/w/dxp/development/configuration-as-code/instance-settings-yaml-configuration-reference lists no style book PID
- Tokens Studio (Figma plugin and platform) rated `yes`: tokens-studio/figma-plugin@2.12.1 packages/tokens-studio-for-figma/src/constants/StorageProviderType.ts:5-10: tokens and $themes live as JSON in a git repository; the Studio CLI keeps the consumed version in .studio.json and studio.lock in the codebase (https://documentation-v2.tokens.studio/cli/overview.html)

#### Row `aut-git-sync` (thematiq matrix, area authoring)

- Capability: Store and version the token source in a Git repository with pull requests.
- Own rating: no; built.state `none`. Built evidence: grep -rniF for a git-backed token source (pull request workflow, git clone/push in lib/Service or lib/Command) found nothing; token sets are uploaded as files (CustomTokenSetController.php:203) or shipped as static CSS in css/tokens/, never sourced from a Git remote
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `partial`: https://microsoft365dsc.com/resources/sharepoint/SPOTheme/: the Microsoft-led open-source Microsoft365DSC declares SharePoint themes (SPOTheme with Palette) as configuration that can be versioned and deployed, with an Azure DevOps integration guide; Entra sign-in branding and the M365 org theme are not among its resources, and there is no built-in git flow
- openDesk theming rated `yes`: opendesk@v1.18.2: the token source is plain files in the helmfile repository (theme.yaml.gotmpl, helmfile/files/theme/**), and docs/enhanced-configuration/gitops.md documents deploying from Git with Argo CD
- Liferay DXP (style books, themes, client extensions) rated `partial`: https://learn.liferay.com/w/dxp/development/customizing-liferays-look-and-feel/using-a-theme-css-client-extension : the theme CSS and its frontend-token-definition.json live in a Liferay Workspace project that can be versioned in Git; style book values live in the database and leave only as ZIP or LAR exports (https://learn.liferay.com/w/dxp/sites/site-appearance/style-books/exporting-and-importing-style-books)
- Tokens Studio (Figma plugin and platform) rated `yes`: tokens-studio/figma-plugin@2.12.1 packages/tokens-studio-for-figma/src/constants/StorageProviderType.ts:5-10 GitHub, GitLab, Azure DevOps, Bitbucket storage (storage/GithubTokenStorage.ts etc.); app/components/PushDialog.tsx:46-60 links to the provider's create-pull-request page
- Rated no or unknown: Nextcloud Theming (built-in app) `no`

#### Row `app-branding-package` (thematiq matrix, area apply)

- Capability: Ship the tokens, templates and the theme stylesheet as one branding package that applies itself when a site is connected.
- Own rating: partial; built.state `built`. Built evidence: lib/Service/ConfigBundleService.php (header comment) exports every instance-wide thematiq value as one bundle for OTAP promotion and applies it on import, but font binaries are not embedded (metadata only; the admin re-uploads fonts on the target), so the package is not complete; overlaps gov-otap-bundle, kept for the self-applying package angle
- Demand: roadmap at https://liferay.atlassian.net/browse/LPD-91941 (moving a full house style between environments or tenants in one unit is what OTAP governance needs)
- Rated no or unknown: Nextcloud Theming (built-in app) `unknown`, Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) `unknown`, openDesk theming `unknown`, Liferay DXP (style books, themes, client extensions) `no`, Tokens Studio (Figma plugin and platform) `unknown`

## What changes

- A branding package: a directory (or a ZIP of it) holding the existing `bundle.json`, the font files, and optionally DTCG token sources that thematiq converts on apply. `occ nldesign:config:export --package <dir>` writes one; `occ nldesign:config:import <dir-or-zip>` applies one, fonts included.
- Declarative mode: `thematiq.config_source` in `config.php` names a package path, for example a volume that Argo CD or Helm fills from Git. Thematiq applies it after upgrades, from a background job, and on `occ nldesign:config:apply`, whenever its content hash changes.
- A failed apply changes nothing and says so on the settings page. A successful apply is audited with the package's Git revision when the checkout provides one.
- While a config source is set, the settings page says the house style is managed from deployment configuration and shows whether the running configuration has drifted from it. An optional lock makes the page read-only.

## Capabilities

### New capabilities

- `theme-as-code`: branding packages, declarative apply, drift and lock.

### Modified capabilities

- `config-portability`: a package carries font binaries, and an import from a package applies them.

## Impact

- `lib/Service/ConfigBundleService.php`: package read and write around the existing `export()` (`:241`) and `import()` (`:309`); fonts through `lib/Service/FontService.php` (app data folder `fonts`, `:77`) and its validator.
- `lib/Command/ConfigExport.php`, `lib/Command/ConfigImport.php` (`:69-140`): package support; new `lib/Command/ConfigApply.php`.
- New `lib/Service/ConfigSourceService.php`, a repair step next to the ones in `appinfo/info.xml:182-233`, and a background job.
- `templates/settings/admin.php`, configuration bundle block (`:587`): managed-by notice, drift, lock.
- Docs: a Helm and Argo CD example.

## Rows

- `gov-config-as-code` (thematiq matrix).
- `aut-git-sync` (thematiq matrix). The row had no `built.owner`; this pass fills it as `ConductionNL/thematiq`.
- `app-branding-package` (thematiq matrix): the missing half, fonts in the package and applying it from deployment configuration.
