# Design: approved mark in the AI assistant

## Where it fits (development b4e7568)

- `lib/Capabilities.php` publishes `logos.default` for the active set (`openspec/specs/theming-capability/spec.md:37-60`). That capability is public and limited to "branding facts already observable by anyone loading the themed login page" with an exact key list, so the mark does not go there.
- `lib/Service/EmailThemingService.php:161-167` holds the organisation name used in the email footer.
- `lib/Controller/CatalogController.php:33,82-83` is the `#[NoAdminRequired]`, session-only read pattern for fleet apps.
- `lib/Service/ConfigBundleService.php:241` and `openspec/specs/config-portability/spec.md:6-20`: every new instance-wide value joins the bundle.
- Sibling: `@conduction/nextcloud-vue` development `c8aa8586` ships `src/components/CnAiCompanion/` (`CnAiCompanion.vue`, `CnAiChatPanel.vue`, `CnAiInput.vue` and others). ADR-034 (hydra) puts the companion in nextcloud-vue, mounted through `CnAppRoot`.

## Decisions

### 1. Thematiq owns the statement, nextcloud-vue draws it

Whether the organisation sanctions the assistant, and with which name and logo, is a branding decision taken where the house style is managed. Drawing it is the panel's job. The endpoint is the contract between the two.

### 2. A session-only endpoint, not the public capability

`GET /apps/thematiq/api/assistant-mark` returns `{enabled, label, organisation, logo: {url, alt}|null}` for signed-in users. `label` is "Approved by {organisation}" translated into the requesting user's language on the server, so the panel needs no translation of its own. When the mark is off the response is `{enabled: false}` and nothing else.

### 3. Off by default, defaults filled from what exists

`assistant_mark_enabled` defaults to off. `assistant_mark_organisation` defaults to the email footer organisation name; `assistant_mark_logo` defaults to the active set's `logos.default`. The toggle cannot be turned on while the organisation name is empty, because "Approved by" with no name says nothing.

### 4. The mark is informative, not a security control

It tells honest users which assistant is the sanctioned one. It does not prove anything to an attacker who controls the page. The settings hint says so, so nobody mistakes it for a control.

## Risks

- A panel that fetches the endpoint on every open adds a request. The contract allows caching for the session; the nextcloud-vue half decides.
- Apps on an older nextcloud-vue simply show no mark.

## Out of scope

- Marks in the Nextcloud Assistant app (`nextcloud/assistant`), which the fleet does not own.
- A mark per assistant agent.
