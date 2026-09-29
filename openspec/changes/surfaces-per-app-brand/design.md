# Design: per-app brand

## Where it fits (development b4e7568)

- `lib/Listener/ThemeInjectionListener.php:188-196` resolves the rendered app id from `TemplateResponse::getApp()`, falling back to `AppThemingService::resolveAppIdFromPath()` (`lib/Service/AppThemingService.php:182`), to run the exclusion guard (`openspec/specs/per-app-theming/spec.md:28-40`). The login page never has an app id.
- `lib/Service/CssInjectionService.php:247` `inject(string $context)`; at `:261` it resolves the token set through `GroupThemingService::resolveTokenSetForRequest()`.
- The header logo is drawn from `--nldesign-logo-url` (`css/systems/nldesign/theme.css:135`, `:303`), which `CssInjectionService` sets per request in an inline layer (`:725-735`), choosing between an uploaded core logo and core's `logo.svg`.
- `openspec/specs/per-group-theming/spec.md:43-60` fixes the resolution order: preview, group, default.
- `lib/Service/AppThemingService.php:53` protects `thematiq`, `settings` and `theming` from exclusion.

## Decisions

### 1. Stored next to the exclusion list

`app_brands` is a JSON object keyed by app id: `{tokenSet, logoLarge|null, logoSmall|null}`. The app must be installed and not excluded; the protected ids cannot be branded, so the settings pages always look like the organisation.

### 2. The app brand sits between preview and group

On a page of a branded app, the app's set wins over the user's group set: the brand belongs to the app, whoever opens it. An active admin preview still wins, so an administrator can try a set on any page. Sessionless pages keep the instance default, as `per-group-theming` requires, because public share pages and the login page have no app brand.

Rejected: group over app. Then a branded participation platform would change colour per municipality, which defeats the purpose.

### 3. The logo goes through the existing variable

For a branded app with a large logo, the inline logo layer sets `--nldesign-logo-url` to that logo instead of the core logo. The small logo is used under the narrow-screen breakpoint that Nextcloud's header already uses. No new selector is added.

### 4. The name stays core's

The app menu label and the page title come from Nextcloud and the app's own `info.xml`. Changing them needs a script that rewrites core pages, which breaks on every release. The admin block says so.

## Risks

- Contrast: an app set with poor contrast next to the instance header. The block shows each mapped set's contrast result, warn-only as everywhere.
- An app renamed or removed: its entry is ignored at resolution and flagged in the block.

## Out of scope

- Per-app favicons (the favicon is core theming's).
- Per-app brands on public pages.
