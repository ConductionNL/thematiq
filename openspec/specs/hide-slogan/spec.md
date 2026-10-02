---
status: done
reviewed_date: 2026-02-28
enriched_date: 2026-03-20
---

# Hide Slogan Specification

## Purpose
Defines the "Hide Slogan" feature that removes the Nextcloud slogan/payoff text from the login page.

Dutch government organizations typically need to present a clean, branded login page without Nextcloud's default slogan ("a safe home for all your data"). When enabled, the footer element on the login page that contains this slogan is completely hidden via a conditionally loaded CSS file.

## Requirements

### Requirement: Configuration Storage
The hide slogan setting MUST be stored in Nextcloud's `IConfig` as a string value with clear on/off semantics.

#### Scenario: Setting stored as enabled
- GIVEN the admin enables the hide slogan feature
- WHEN `POST /apps/nldesign/settings/slogan` is called with `hideSlogan=true`
- THEN `IConfig::setAppValue('nldesign', 'hide_slogan', '1')` MUST be called
- AND the response MUST be JSON with `{"status": "ok", "hideSlogan": true}`

#### Scenario: Setting stored as disabled
- GIVEN the admin disables the hide slogan feature
- WHEN `POST /apps/nldesign/settings/slogan` is called with `hideSlogan=false`
- THEN `IConfig::setAppValue('nldesign', 'hide_slogan', '0')` MUST be called
- AND the response MUST be JSON with `{"status": "ok", "hideSlogan": false}`

#### Scenario: Default value when not configured
@e2e exclude a browser cannot remove a stored app value, so it cannot reach the never-configured state on an instance where the setting was ever saved; PHPUnit tests/Unit/Service/CssInjectionServiceTest.php::testTogglesAbsentFromConfigDefaultToOff asserts an absent key loads no hide-slogan stylesheet
- GIVEN no value has been set for `nldesign:hide_slogan`
- WHEN the setting is read during boot
- THEN the default value MUST be `'0'` (disabled)
- AND the slogan MUST be visible on the login page

#### Scenario: Setting persists across app restarts
@e2e exclude a server restart cannot be driven from a browser; the value is the IConfig row, and PHPUnit tests/Unit/Controller/SettingsControllerEndpointsTest.php::testATogglePersistsTheOnValue asserts the endpoint stores the string '1' there
- GIVEN the admin has enabled the hide slogan setting
- WHEN the Nextcloud server is restarted
- THEN the setting MUST still be `'1'` in IConfig
- AND the slogan MUST remain hidden on the login page

### Requirement: Conditional CSS Loading
The hide-slogan CSS file MUST only be loaded when the feature is enabled, minimizing unnecessary CSS injection.

#### Scenario: Feature enabled loads CSS
- GIVEN `IConfig` returns `'1'` for `hide_slogan`
- WHEN `Application::injectThemeCSS()` runs during boot
- THEN `\OCP\Util::addStyle('nldesign', 'hide-slogan')` MUST be called
- AND the CSS file MUST be loaded after all core CSS layers and the custom-overrides layer

#### Scenario: Feature disabled skips CSS
- GIVEN `IConfig` returns `'0'` for `hide_slogan`
- WHEN `Application::injectThemeCSS()` runs during boot
- THEN `hide-slogan` CSS MUST NOT be loaded
- AND no slogan-hiding styles MUST be injected into the page

#### Scenario: CSS loading position in cascade
- GIVEN the hide-slogan CSS is loaded
- WHEN the CSS cascade is evaluated
- THEN hide-slogan MUST load after Layer 7 (element-overrides) and custom-overrides
- AND before any user-agent default styles could interfere
- AND the `!important` declarations MUST ensure the hiding takes effect regardless of other styles

### Requirement: Slogan Element Hiding
When the feature is enabled, the login page footer containing the slogan MUST be completely hidden from both visual display and the accessibility tree.

#### Scenario: Footer element hidden with display none
- GIVEN the hide-slogan CSS is loaded
- WHEN the login page renders
- THEN `footer.guest-box` MUST have `display: none !important`
- AND `visibility: hidden !important`

#### Scenario: Multiple selector coverage for robustness
- GIVEN the hide-slogan CSS is loaded
- WHEN the login page renders
- THEN the CSS MUST target these selectors for maximum coverage:
  - `footer.guest-box` (direct match)
  - `#body-login footer.guest-box` (login page context)
  - `body.body-login-container footer.guest-box` (container class context)
- AND all three selectors MUST apply the same `display: none !important` and `visibility: hidden !important`

#### Scenario: Slogan visible when feature disabled
- GIVEN the hide-slogan CSS is NOT loaded
- WHEN the login page renders
- THEN the `footer.guest-box` element MUST display normally
- AND the Nextcloud slogan/payoff text MUST be visible
- AND no residual hiding styles MUST affect the footer

### Requirement: Login Page Only Scope
The hide slogan CSS MUST only affect the login page footer and MUST NOT affect other footer elements or pages.

#### Scenario: Non-login page footers unaffected
- GIVEN the hide-slogan CSS is loaded
- AND the user is on a non-login page (e.g. Files app)
- WHEN the page renders
- THEN no footer elements MUST be hidden
- AND the selectors MUST be specific to `.guest-box` footer elements (only present on login/guest pages)

#### Scenario: Other guest-box elements unaffected
- GIVEN the hide-slogan CSS is loaded
- AND the login page has a main `.guest-box` element (the login form)
- WHEN the page renders
- THEN only the `footer.guest-box` element MUST be hidden
- AND the main `div.guest-box` or other non-footer guest-box elements MUST remain visible

#### Scenario: Login page layout preserved
- GIVEN the slogan footer is hidden
- WHEN the login page layout is computed
- THEN `display: none` MUST remove the element from the layout flow
- AND no empty space MUST remain where the slogan was
- AND the login form vertical centering MUST remain correct

### Requirement: Boolean Conversion
The controller MUST correctly convert the boolean API parameter to a string for IConfig storage, using strict type comparison.

#### Scenario: True boolean converted to string '1'
- GIVEN the API receives `hideSlogan` as boolean `true`
- WHEN `setSloganSetting(true)` is called
- THEN `saveBooleanSetting('hide_slogan', true)` MUST be called
- AND the comparison MUST use strict equality (`=== true`)
- AND the value stored in IConfig MUST be the string `'1'`

#### Scenario: False boolean converted to string '0'
- GIVEN the API receives `hideSlogan` as boolean `false`
- WHEN `setSloganSetting(false)` is called
- THEN `saveBooleanSetting('hide_slogan', false)` MUST be called
- AND the value stored in IConfig MUST be the string `'0'`

#### Scenario: Boot phase reads and compares correctly
@e2e exclude the endpoint binds a boolean, so a browser can only ever store '1' or '0', never the loose values this scenario is about; PHPUnit tests/Unit/Service/CssInjectionServiceTest.php::testConditionalStylesheetsIgnoreLooseTruthyValues asserts 'yes', 'true', 'on', '01' and ' 1' load nothing
- GIVEN `IConfig` stores `'1'` for `hide_slogan`
- WHEN `Application::injectThemeCSS()` reads the value
- THEN it MUST compare with `=== '1'` to get boolean `true`
- AND it MUST NOT use loose comparison that could match other truthy values (e.g., `'yes'`, `'true'`, `1`)

### Requirement: API Endpoint
The app MUST expose an admin-only API endpoint for toggling the hide slogan setting.

#### Scenario: Toggle slogan hiding on
- GIVEN the admin is authenticated
- WHEN `POST /apps/nldesign/settings/slogan` is called with `hideSlogan=true`
- THEN the setting MUST be stored as `'1'` in IConfig
- AND the response MUST confirm success with `{"status": "ok", "hideSlogan": true}`

#### Scenario: Toggle slogan hiding off
- GIVEN the admin is authenticated
- WHEN `POST /apps/nldesign/settings/slogan` is called with `hideSlogan=false`
- THEN the setting MUST be stored as `'0'` in IConfig
- AND the response MUST confirm success with `{"status": "ok", "hideSlogan": false}`

#### Scenario: Non-admin access denied
- GIVEN a non-admin user is authenticated
- WHEN `POST /apps/nldesign/settings/slogan` is called
- THEN the request MUST be rejected by the `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)` annotation
- AND the setting MUST NOT be modified

#### Scenario: Route registration
- GIVEN the app's routes configuration
- WHEN routes are loaded from `appinfo/routes.php`
- THEN a POST route for `/settings/slogan` MUST be mapped to `settings#setSloganSetting`

### Requirement: Dual Hiding Strategy
The hide slogan CSS MUST use both `display: none` and `visibility: hidden` to ensure complete removal across different rendering contexts.

#### Scenario: Both hiding mechanisms applied
- GIVEN the hide-slogan CSS is loaded
- WHEN the selectors are processed
- THEN `display: none !important` MUST be set (removes from layout flow and accessibility tree)
- AND `visibility: hidden !important` MUST be set (ensures no visual trace in edge cases)
- AND both properties MUST use `!important` to override any Nextcloud core styles or third-party theme styles

#### Scenario: Accessibility tree impact
- GIVEN the slogan footer is hidden with `display: none`
- WHEN a screen reader traverses the login page
- THEN the slogan text MUST NOT be announced
- AND this is acceptable because the slogan is decorative, not functional content

#### Scenario: Print stylesheet compatibility
- GIVEN the slogan is hidden on screen
- WHEN the login page is printed
- THEN the slogan MUST also be hidden in print (because `display: none !important` applies to all media)

### Requirement: Admin Settings Panel Integration
The hide slogan checkbox in the admin settings MUST reflect and control the current state.

#### Scenario: Checkbox reflects current state on load
- GIVEN the hide slogan setting is enabled
- WHEN the settings panel loads
- THEN the `#nldesign-hide-slogan` checkbox MUST be checked
- AND the checkbox MUST have `class="checkbox"` for Nextcloud form styling

#### Scenario: Checkbox change triggers save
- GIVEN the admin unchecks the hide slogan checkbox
- WHEN the change event fires in JavaScript
- THEN `POST /apps/nldesign/settings/slogan` MUST be called with `hideSlogan=false`
- AND on success, the change takes effect on next page load (CSS is injected at boot time)

#### Scenario: Checkbox label is localized
- GIVEN the settings panel renders
- THEN the checkbox label MUST read "Hide Nextcloud slogan/payoff on login page" (via `$l->t()`)
- AND the label MUST be linked to the checkbox via `for="nldesign-hide-slogan"`

### Requirement: Government Branding Compliance
The hide slogan feature MUST support Dutch government branding requirements for clean login pages.

#### Scenario: Rijkshuisstijl login page compliance
- GIVEN the rijkshuisstijl token set is active
- AND the hide slogan feature is enabled
- WHEN the login page renders
- THEN no Nextcloud branding text MUST appear below the login form
- AND the login page MUST present only the government organization's branding
- AND the clean appearance MUST align with Rijkshuisstijl guidelines

#### Scenario: Municipality login page compliance
- GIVEN a gemeente token set (e.g., amsterdam) is active
- AND the hide slogan feature is enabled
- WHEN the login page renders
- THEN the municipality's visual identity MUST be the sole branding on the page
- AND the Nextcloud slogan MUST NOT distract from the government branding

#### Scenario: Feature works with all token sets
- GIVEN any token set is active (including stock Nextcloud)
- WHEN the hide slogan feature is enabled
- THEN the slogan MUST be hidden regardless of which token set is selected
- AND the feature MUST function independently of the token set choice

### Requirement: Effect Requires Page Reload
The hide slogan setting takes effect at boot time (CSS injection), so changes MUST take effect on the next page load.

#### Scenario: Setting change not immediate
- GIVEN the admin enables hide slogan in the settings panel
- WHEN the API call succeeds
- THEN the current page MUST NOT immediately hide the slogan on the login page
- AND the CSS MUST be injected on the next full page load via `Application::boot()`

#### Scenario: Admin sees effect by navigating to login page
- GIVEN the admin has enabled the hide slogan setting
- WHEN the admin opens the login page in a new tab or incognito window
- THEN the slogan MUST be hidden
- AND this confirms the setting is active

## Current Implementation Status

**Fully implemented:**
- Configuration storage: `Application.php` reads `hide_slogan` from `IConfig` with default `'0'`, compares with `=== '1'` (line 85)
- API endpoint: `POST /apps/nldesign/settings/slogan` mapped in `appinfo/routes.php` (line 14) to `SettingsController::setSloganSetting()`
- Boolean conversion: `saveBooleanSetting('hide_slogan', $hideSlogan)` uses strict `=== true` to convert to `'1'`/`'0'` string (`lib/Controller/SettingsController.php` lines 162-170)
- Conditional CSS loading: `Application::injectThemeCSS()` loads `hide-slogan` CSS only when `$hideSlogan === true` (lines 112-114)
- CSS file: `css/hide-slogan.css` targets `footer.guest-box`, `#body-login footer.guest-box`, and `body.body-login-container footer.guest-box` with both `display: none !important` and `visibility: hidden !important`
- Admin-only access: `@AuthorizedAdminSetting(settings=OCA\Thematiq\Settings\Admin)` annotation on `setSloganSetting()`
- Settings panel checkbox: `templates/settings/admin.php` renders `#nldesign-hide-slogan` checkbox with correct checked state and localized label text
- JavaScript handler: `js/admin.js` calls save on checkbox change via `POST /apps/nldesign/settings/slogan`

**Not yet implemented:**
- All requirements in this spec are fully implemented.

## Standards & References
- Rijkshuisstijl guidelines: Dutch government login pages should present clean, branded appearance without third-party slogans
- WCAG 2.1 AA: hiding decorative text with `display: none` correctly removes elements from the accessibility tree (this is acceptable for non-functional slogan text)
- Nextcloud login page structure: `footer.guest-box` is the standard container for the slogan on guest/login pages
- OWASP: admin-only endpoint protects against unauthorized setting changes
