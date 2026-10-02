---
status: done
reviewed_date: 2026-02-28
enriched_date: 2026-03-20
---

# Show Menu Labels Specification

## Purpose
Defines the "Show Menu Labels" feature that replaces app menu icons in the Nextcloud header with text labels.

When enabled, the header navigation displays application names (e.g. "Files", "Mail", "Calendar") instead of icons, improving discoverability and accessibility for users unfamiliar with Nextcloud's icon-based navigation. This feature aligns with Dutch government UX guidelines that prioritize clarity and readability over icon recognition.

On Nextcloud 32 each header entry swaps its icon for its name. Nextcloud 33 and newer replaced those entries with a waffle menu; there the setting keeps the current app's name next to the waffle at every width and keeps every tile in the app grid labelled.

## Requirements

### Requirement: Configuration Storage
The show menu labels setting MUST be stored in Nextcloud's `IConfig` as a string value with clear on/off semantics.

#### Scenario: Setting stored as enabled
- GIVEN the admin enables the show menu labels feature
- WHEN `POST /apps/nldesign/settings/menulabels` is called with `showMenuLabels=true`
- THEN `saveBooleanSetting('show_menu_labels', true)` MUST be called
- AND `IConfig::setAppValue('nldesign', 'show_menu_labels', '1')` MUST be stored
- AND the response MUST be JSON with `{"status": "ok", "showMenuLabels": true}`

#### Scenario: Setting stored as disabled
- GIVEN the admin disables the show menu labels feature
- WHEN `POST /apps/nldesign/settings/menulabels` is called with `showMenuLabels=false`
- THEN `IConfig::setAppValue('nldesign', 'show_menu_labels', '0')` MUST be called
- AND the response MUST be JSON with `{"status": "ok", "showMenuLabels": false}`

#### Scenario: Default value when not configured
@e2e exclude a browser cannot remove a stored app value, so it cannot reach the never-configured state on an instance where the setting was ever saved; PHPUnit tests/Unit/Service/CssInjectionServiceTest.php::testTogglesAbsentFromConfigDefaultToOff asserts an absent key loads no show-menu-labels stylesheet
- GIVEN no value has been set for `nldesign:show_menu_labels`
- WHEN the setting is read during boot
- THEN the default value MUST be `'0'` (disabled)
- AND menu icons MUST be displayed normally (Nextcloud default behavior)

#### Scenario: Setting persists across restarts
@e2e exclude a server restart cannot be driven from a browser; the value is the IConfig row, and PHPUnit tests/Unit/Controller/SettingsControllerEndpointsTest.php::testATogglePersistsTheOnValue asserts the endpoint stores the string '1' there
- GIVEN the admin has enabled menu labels
- WHEN the Nextcloud server restarts
- THEN the setting MUST remain `'1'` in IConfig
- AND menu labels MUST continue to display on all pages

### Requirement: Conditional CSS Loading
The show-menu-labels CSS file MUST only be loaded when the feature is enabled.

#### Scenario: Feature enabled loads CSS
- GIVEN `IConfig` returns `'1'` for `show_menu_labels`
- WHEN `Application::injectThemeCSS()` runs during boot
- THEN `\OCP\Util::addStyle('nldesign', 'show-menu-labels')` MUST be called
- AND the CSS file MUST be loaded after all core layers and custom-overrides

#### Scenario: Feature disabled skips CSS
- GIVEN `IConfig` returns `'0'` for `show_menu_labels`
- WHEN `Application::injectThemeCSS()` runs during boot
- THEN `show-menu-labels` CSS MUST NOT be loaded
- AND Nextcloud's default icon-based menu MUST render normally

#### Scenario: CSS loading order relative to other conditionals
- GIVEN both hide_slogan and show_menu_labels are enabled
- WHEN the CSS files are loaded
- THEN hide-slogan MUST be loaded before show-menu-labels (matching the code order in `Application.php`)
- AND both MUST load after the custom-overrides layer

### Requirement: Icon Hiding
When the feature is enabled, app menu icons MUST be hidden from both visual display and layout.

#### Scenario: App menu icons hidden
- GIVEN the show-menu-labels CSS is loaded
- WHEN the header navigation renders
- THEN `#header nav.app-menu .app-menu-icon` MUST have `display: none !important`
- AND `#header nav.app-menu .app-menu-entry__icon` MUST have `display: none !important`
- AND both selectors MUST also have `visibility: hidden !important`

#### Scenario: Icons hidden for all apps
- GIVEN the show-menu-labels CSS is loaded
- AND there are 10 apps in the menu
- WHEN the header renders
- THEN all 10 app icons MUST be hidden
- AND this MUST apply to both built-in Nextcloud apps and third-party apps

#### Scenario: Menu overflow icons preserved
- GIVEN the show-menu-labels CSS is loaded
- AND there are more apps than fit in the header
- WHEN the overflow/more menu button renders
- THEN the overflow trigger button MUST still function
- AND the dropdown menu MUST still be accessible

### Requirement: Label Display and Typography
When the feature is enabled, app menu labels MUST be visible and properly styled for readability.

#### Scenario: Labels made visible
- GIVEN the show-menu-labels CSS is loaded
- WHEN the header navigation renders
- THEN `#header nav.app-menu .app-menu-entry__label` MUST have `display: inline-block !important`
- AND `visibility: visible !important`
- AND `opacity: 1 !important`

#### Scenario: Label typography
- GIVEN labels are visible
- WHEN the styling is applied
- THEN font-size MUST be `14px`
- AND font-weight MUST be `400` for normal items
- AND font-weight MUST be `600` for the active item (`.app-menu-entry--active .app-menu-entry__label`)
- AND white-space MUST be `nowrap` to prevent text wrapping
- AND line-height MUST be `1.4` for readability

#### Scenario: Label positioning overrides Nextcloud defaults
- GIVEN labels are visible
- WHEN the styling is applied
- THEN `position` MUST be `static` (overriding any absolute positioning from Nextcloud)
- AND `transform` MUST be `none` (removing any transforms)
- AND `max-width` MUST be `none` (preventing truncation)
- AND `text-align` MUST be `center`

#### Scenario: Label padding for spacing
- GIVEN labels are visible
- WHEN the styling is applied
- THEN each label MUST have `padding: 0 8px` for horizontal spacing between labels
- AND `vertical-align: middle` for vertical alignment within the header

#### Scenario: Labels use the NL Design font
- GIVEN labels are visible and the nldesign design system is active
- WHEN text is rendered
- THEN the font-family MUST inherit from `--nldesign-font-family` (Fira Sans) via the element-overrides layer
- AND labels MUST match the overall application typography

### Requirement: Menu Entry Layout
When labels are shown, menu entries MUST be properly sized and laid out to accommodate text.

#### Scenario: Menu entry dimensions
- GIVEN the show-menu-labels CSS is loaded
- WHEN menu entries render
- THEN `.app-menu-entry` MUST have `height: var(--header-height)` for full header height
- AND `min-width: 80px` for minimum label space
- AND `width: auto` to accommodate label text of varying lengths
- AND `flex-shrink: 0` to prevent compression when header space is limited

#### Scenario: Menu entry link layout
- GIVEN labels are visible
- WHEN `.app-menu-entry__link` renders
- THEN it MUST have `display: flex`, `flex-direction: column`, `align-items: center`, `justify-content: center`
- AND `height: 100%` for full entry height
- AND `padding: 0` to reset default Nextcloud padding

#### Scenario: Menu stretches to accommodate all labels
- GIVEN there are 8 apps with labels of varying lengths
- WHEN the header menu renders
- THEN each entry MUST auto-size to its label width (minimum 80px)
- AND the flex container MUST not compress entries below their min-width

### Requirement: Active Item Indicator
When labels are shown, the active item MUST be indicated by bold text weight rather than the default Nextcloud indicator.

#### Scenario: Default active indicator removed
- GIVEN the show-menu-labels CSS is loaded
- AND an app menu entry has the `app-menu-entry--active` class
- WHEN the `::before` pseudo-element renders
- THEN `background-color` MUST be `transparent !important`
- AND `opacity` MUST be `0 !important`
- AND the default Nextcloud black dot indicator MUST be invisible

#### Scenario: Active item distinguished by font weight
- GIVEN the active app has the `app-menu-entry--active` class
- WHEN the label renders
- THEN the label MUST have `font-weight: 600` (semi-bold)
- AND inactive labels MUST have `font-weight: 400` (normal)
- AND this weight difference MUST be the sole visual indicator of the active state

#### Scenario: Active state visible on all backgrounds
- GIVEN the header background varies by token set (white, dark, etc.)
- WHEN the active label is displayed
- THEN the font-weight difference MUST be perceivable regardless of background color
- AND the text color MUST be inherited from the header text color token

### Requirement: API Endpoint
The app MUST expose an admin-only API endpoint for toggling the show menu labels setting.

#### Scenario: Toggle menu labels on
- GIVEN the admin is authenticated
- WHEN `POST /apps/nldesign/settings/menulabels` is called with `showMenuLabels=true`
- THEN the setting MUST be stored as `'1'` in IConfig
- AND the response MUST confirm success with `{"status": "ok", "showMenuLabels": true}`

#### Scenario: Toggle menu labels off
- GIVEN the admin is authenticated
- WHEN `POST /apps/nldesign/settings/menulabels` is called with `showMenuLabels=false`
- THEN the setting MUST be stored as `'0'` in IConfig
- AND the response MUST confirm success with `{"status": "ok", "showMenuLabels": false}`

#### Scenario: Non-admin access denied
- GIVEN a non-admin user is authenticated
- WHEN `POST /apps/nldesign/settings/menulabels` is called
- THEN the request MUST be rejected by the `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)` annotation
- AND the setting MUST NOT be modified

#### Scenario: Route registration
- GIVEN the app's routes configuration
- WHEN routes are loaded from `appinfo/routes.php`
- THEN a POST route for `/settings/menulabels` MUST be mapped to `settings#setMenuLabelsSetting`

### Requirement: Admin Settings Panel Integration
The settings panel MUST include a checkbox that reflects and controls the menu labels setting.

#### Scenario: Checkbox reflects current state on load
- GIVEN the show menu labels setting is enabled
- WHEN the settings panel loads
- THEN the `#nldesign-show-menu-labels` checkbox MUST be checked

#### Scenario: Checkbox change triggers save
- GIVEN the admin toggles the show menu labels checkbox
- WHEN the change event fires in JavaScript
- THEN `POST /apps/nldesign/settings/menulabels` MUST be called with the new boolean value

#### Scenario: Checkbox label is localized and accessible
- GIVEN the settings panel renders
- THEN the checkbox label MUST read "Show text labels in app menu (hide icons)" (via `$l->t()`)
- AND the label MUST have `for="nldesign-show-menu-labels"` for accessibility

### Requirement: Accessibility Improvement
The menu labels feature MUST improve accessibility by providing text-based navigation alternatives.

#### Scenario: Screen reader improvement
- GIVEN the show-menu-labels feature is enabled
- WHEN a screen reader user navigates the header menu
- THEN each menu item MUST have visible text that matches the `aria-label` or accessible name
- AND the visible label MUST improve discoverability compared to icon-only navigation

#### Scenario: Cognitive accessibility
- GIVEN users unfamiliar with Nextcloud's icon set
- WHEN they navigate the header menu with labels enabled
- THEN they MUST be able to identify each app by its text name
- AND this MUST reduce the cognitive load of memorizing icon meanings

#### Scenario: Feature satisfies WCAG guidelines
- GIVEN the show-menu-labels CSS is loaded
- WHEN the header navigation renders
- THEN visible labels MUST satisfy WCAG 2.1 AA SC 1.3.1 (Info and Relationships) by providing explicit text
- AND labels MUST satisfy SC 3.3.2 (Labels or Instructions) by making navigation items self-describing

### Requirement: Responsive Behavior
The menu labels feature MUST handle varying header widths without breaking the layout.

#### Scenario: Labels on wide viewport
- GIVEN the viewport is wider than 1200px
- AND 8 apps are installed
- WHEN the header renders with labels
- THEN all labels MUST be fully visible
- AND no labels MUST be truncated

#### Scenario: Labels on narrow viewport
- GIVEN the viewport is narrower than 768px
- AND many apps are installed
- WHEN the header renders with labels
- THEN the flex container with `flex-shrink: 0` MAY cause horizontal overflow
- AND the Nextcloud overflow menu MUST accommodate apps that do not fit

#### Scenario: Labels with nowrap prevent wrapping
- GIVEN a label text is "Zaakafhandelcomponent" (20+ characters)
- WHEN the entry renders
- THEN `white-space: nowrap` MUST prevent the text from wrapping to a second line
- AND the entry MUST expand horizontally to fit the full text

### Requirement: Interaction with Hide Slogan Feature
The menu labels and hide slogan features MUST be independently configurable and MUST NOT interfere with each other.

#### Scenario: Both features enabled simultaneously
- GIVEN hide slogan is enabled AND show menu labels is enabled
- WHEN a user navigates from the login page to the dashboard
- THEN the login page MUST have the slogan hidden
- AND the dashboard header MUST show text labels instead of icons
- AND both features MUST function correctly together

#### Scenario: Only menu labels enabled
- GIVEN hide slogan is disabled AND show menu labels is enabled
- WHEN the user is on the login page
- THEN the slogan MUST be visible
- AND after logging in, the header MUST show text labels

### Requirement: Waffle menu labels on Nextcloud 33 and newer
From Nextcloud 33 the header has no `app-menu-entry` elements: core `AppMenu.vue` renders a waffle button (`.app-menu__waffle`), a button with the current app's icon and name (`.app-menu__current-app`, `.app-menu__current-app-name`), and a popover grid of `.app-item` tiles, each with an `.app-item__label` (`AppMenuItem.vue`). The show-menu-labels stylesheet MUST keep the app names visible on that menu, and MUST keep its Nextcloud 32 rules, because the app supports both. Each half matches nothing on the other server.

#### Scenario: Current app name shown next to the waffle at every width
- GIVEN the show-menu-labels CSS is loaded on Nextcloud 33 or newer
- AND the viewport is 1024px wide or narrower, where core hides the current-app button with `display: none !important`
- WHEN the header renders
- THEN `#header nav.app-menu .app-menu__current-app` MUST have `display: flex !important`, outside any media query, so the button shows at every width
- AND `#header nav.app-menu .app-menu__current-app-name` MUST have `display: inline-block`, `visibility: visible` and `opacity: 1`, each `!important`
- AND the name MUST keep core's `max-width`, so a long name ellipsizes instead of pushing the header icons off screen
- AND the waffle MUST stay, because it is the only way to open the app grid

#### Scenario: Grid tiles keep their labels
- GIVEN the show-menu-labels CSS is loaded on Nextcloud 33 or newer
- WHEN the user opens the waffle menu
- THEN every `.app-menu__popover .app-item__label` MUST have `display: block`, `visibility: visible` and `opacity: 1`, each `!important`
- AND the selector MUST NOT start with `#header`, because the popover is rendered outside the header
- AND the active app's label (`.app-item--active .app-item__label`) MUST have `font-weight: 600`, as the active entry does on Nextcloud 32

## Current Implementation Status

**Fully implemented:**
- Configuration storage: `Application.php` reads `show_menu_labels` from `IConfig` with default `'0'`, compares with `=== '1'` (line 86)
- API endpoint: `POST /apps/nldesign/settings/menulabels` mapped in `appinfo/routes.php` (line 15) to `SettingsController::setMenuLabelsSetting()`
- Boolean conversion: `saveBooleanSetting('show_menu_labels', $showMenuLabels)` uses strict `=== true` (lines 197-202)
- Conditional CSS loading: `Application::injectThemeCSS()` loads `show-menu-labels` CSS only when `$showMenuLabels === true` (lines 116-118)
- CSS file: `css/show-menu-labels.css` (66 lines) implements all requirements:
  - Icons hidden: `.app-menu-icon` and `.app-menu-entry__icon` with `display: none !important` and `visibility: hidden !important` (lines 10-14)
  - Labels visible: `.app-menu-entry__label` with `display: inline-block !important`, `visibility: visible !important`, `opacity: 1 !important` (lines 17-35)
  - Label typography: `font-size: 14px`, `font-weight: 400` normal / `600` active, `white-space: nowrap`, `line-height: 1.4` (lines 21-25, 38-40)
  - Label positioning: `position: static`, `transform: none`, `max-width: none`, `text-align: center`, `padding: 0 8px`, `vertical-align: middle` (lines 23-35)
  - Menu entry dimensions: `height: var(--header-height)`, `min-width: 80px`, `width: auto`, `flex-shrink: 0` (lines 59-64)
  - Menu entry link layout: `display: flex`, `flex-direction: column`, `align-items: center`, `justify-content: center`, `height: 100%`, `padding: 0` (lines 49-56)
  - Active indicator removed: `.app-menu-entry--active::before` with `background-color: transparent !important` and `opacity: 0 !important` (lines 43-46)
- Admin-only access: `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)` annotation on `setMenuLabelsSetting()`
- Settings panel checkbox: `templates/settings/admin.php` renders `#nldesign-show-menu-labels` checkbox with correct checked state and localized label text
- JavaScript handler: `js/admin.js` calls save on checkbox change

- Nextcloud 33+ waffle menu (#895): the end of `css/show-menu-labels.css` keeps `.app-menu__current-app` shown below 1024px, shows `.app-menu__current-app-name`, and keeps `.app-menu__popover .app-item__label` visible, with the active tile at 600

**Not yet implemented:**
- All requirements in this spec are fully implemented.

## Standards & References
- WCAG 2.1 AA: Text labels improve discoverability and accessibility (SC 1.3.1 Info and Relationships, SC 3.3.2 Labels or Instructions)
- NL Design System: Government users may be unfamiliar with Nextcloud icon conventions; text labels align with government UX guidelines for clarity
- Nextcloud header navigation: `.app-menu-entry`, `.app-menu-entry__icon`, `.app-menu-entry__label` are standard Nextcloud component classes from `AppMenuEntry.vue` (Nextcloud 32); `.app-menu__waffle`, `.app-menu__current-app`, `.app-menu__current-app-name` and `.app-item__label` come from `AppMenu.vue` and `AppMenuItem.vue` (Nextcloud 33 and newer)
- Rijkshuisstijl: Dutch government guidelines favor explicit, readable navigation over icon-based shortcuts
