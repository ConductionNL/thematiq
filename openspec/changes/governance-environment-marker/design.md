# Design: environment marker

## Where it fits (development b4e7568)

- `lib/Listener/ThemeInjectionListener.php:115-130` handles `BeforeLoginTemplateRenderedEvent` (context `login`, line 117-119) and `BeforeTemplateRenderedEvent`. For the latter it returns early when the app is excluded from theming (`isThemingDisabledForResponse()`, line 126) before it calls `CssInjectionService::inject()` (line 130).
- `lib/Service/CssInjectionService.php:296-301` runs the preview banner as the last layer, through `ThemePreviewBannerService::inject()`.
- `lib/Service/ThemePreviewBannerService.php` is the pattern to copy: it fails open, provides one initial-state payload and emits a script and a stylesheet pair (`emitPreviewAssets()`, `js/preview-banner.js`, `css/preview-banner.css`).
- `lib/Service/ConfigBundleService.php:241-254` exports the bundle; `openspec/specs/config-portability/spec.md:6-20` lists what the bundle MUST NOT contain and holds the ratchet that every future instance-wide value joins the bundle.
- `templates/settings/admin.php:56` opens the theming section; the environment line goes under its heading.

## Decisions

### 1. The environment lives in config.php, not in app config

`thematiq.environment` is a system value, set with `occ config:system:set thematiq.environment --value=test` or by the deployment. The whole point is to tell copies apart. OTAP copies are usually made by restoring the production database into acceptance or test. An app config value would travel with that copy and label test as production, which is the exact mistake the marker exists to prevent. `config.php` belongs to one server and is not in the database.

Rejected: an app config value with an admin form. It is easier to set, and wrong after the first database restore.

### 2. It stays out of the configuration bundle

The bundle exists to make environments look the same. The environment is the one thing that must differ, like `installed_version` and per-user preview state that the bundle already excludes. The config-portability requirement is modified to say so, so the ratchet rule does not later pull it in.

### 3. Injected before the per-app guard, on every render context

The marker is a safety signal, not a theme. It is emitted in `ThemeInjectionListener::handle()` before the exclusion guard, for the login context and for every `BeforeTemplateRenderedEvent`, so excluded apps, the `none` design system and public share pages all show it. It fails open like the preview banner: an error renders no marker and logs a warning, it never breaks the page.

### 4. Text plus colour, fixed per environment

Allowed values and their label: `development` "Development environment", `test` "Test environment", `acceptance` "Acceptance environment". `production` and an unset value render nothing. An unknown value renders the label "Unknown environment" with the test styling and logs a warning, because a typo in `config.php` must not silently look like production.

Each environment has one fixed stripe colour and a label with a text colour that reaches 4.5:1 against it in light and dark mode. The colours are not taken from the house style: a brand whose primary colour happens to be amber must not make test look like the brand.

### 5. The page title carries the environment too

`js/environment-marker.js` prefixes `document.title` with the short label (`[Test]`), so browser tabs and bookmarks show it. The stripe itself is a `role="note"` element with the label as text, placed before the header so it is the first thing a screen reader user meets.

## Risks

- A deployment that never sets the value shows nothing, which is the same as today. The settings page line says "not set" and how to set it, so the gap is visible to the administrator.
- The stripe takes a few pixels of height. It is fixed and thin, and it must not cover the header's own controls.

## Out of scope

- Different house styles per environment. The bundle keeps them identical by design.
- Marking emails sent from a test server. Noted for a later change.
