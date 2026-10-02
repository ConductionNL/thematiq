# Approved mark in the AI assistant

## Why

Public servants are told to use only the AI assistant their organisation sanctioned. Inside the workspace every assistant panel looks alike, and a look-alike is one browser extension away. Microsoft 365 answers this in Copilot: the logo from the organisation's theming settings sits in the chat footer with a fixed label, "Approved by". The fleet's own assistant, `CnAiCompanion` in `@conduction/nextcloud-vue` (ADR-034), shows no organisation mark.

#### Row `sur-assistant-logo` (thematiq matrix, area surfaces)

- Capability: Show the organisation's logo as an approved mark inside the AI assistant, so users can see the assistant is the one their organisation sanctioned.
- Own rating: no; built.state `none`. Built evidence: grep -rniF assistant lib templates js finds no hook into the Nextcloud Assistant; thematiq restyles its colours through the shared CSS variables like any app (sur-all-pages) but shows no organisation mark inside it
- Demand: roadmap at https://www.microsoft.com/microsoft-365/roadmap?id=555852 (Public servants must be able to tell a sanctioned assistant from a look-alike; a brand mark in the assistant is a trust signal.)
- Microsoft 365 organisational branding (Entra company branding, Microsoft 365 themes, SharePoint brand center) rated `yes`: https://www.microsoft.com/microsoft-365/roadmap?id=555852 (rolling out, GA 2026-08): the Microsoft 365 Copilot app shows the logo 'already managed in the Microsoft Admin Center theming settings' in the chat footer 'paired with a fixed label: Approved by'
- Rated no or unknown: Nextcloud Theming (built-in app) `no`, openDesk theming `no`, Liferay DXP (style books, themes, client extensions) `unknown`, Tokens Studio (Figma plugin and platform) `no`

## What changes

- A small "AI assistant" block in Settings > Administration > Theming: a toggle to show the approved mark, the organisation name (defaulting to the email footer organisation name), and the logo (defaulting to the active house style logo).
- `GET /api/assistant-mark` gives signed-in users the mark: whether it is on, the label "Approved by <organisation>" in the user's language, and the logo URL with its alternative text.
- The mark is off by default. Turning it on is a statement by the organisation, not a default of the software.
- Sibling half in `ConductionNL/nextcloud-vue`: `CnAiChatPanel` renders the mark in its footer when thematiq is installed and the mark is on, and renders nothing otherwise.

## Capabilities

### New capabilities

- `assistant-approved-mark`: the setting, the endpoint and the contract with the assistant panel.

### Modified capabilities

- None. The public theming capability stays as it is, because the mark is for signed-in users only.

## Impact

- New `lib/Service/AssistantMarkService.php` and a controller method on a new `AssistantMarkController` (`#[NoAdminRequired]` read, `#[AuthorizedAdminSetting]` write).
- Reads `lib/Service/EmailThemingService.php` `getFooterConfig()` (`:161`) and the active set's `logos.default` as published by `lib/Capabilities.php`.
- `templates/settings/admin.php` and `js/admin.js`: the AI assistant block. Three app config keys, all in the configuration bundle.
- Sibling: `ConductionNL/nextcloud-vue` `src/components/CnAiCompanion/CnAiChatPanel.vue` (development `c8aa8586`).

## Rows

- `sur-assistant-logo` (thematiq matrix).
