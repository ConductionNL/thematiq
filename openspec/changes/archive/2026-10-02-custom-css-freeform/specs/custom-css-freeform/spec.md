## ADDED Requirements

### Requirement: Custom CSS File Gated By Appconfig Flag
The system MUST maintain a `custom-css.css` file in the nldesign app's CSS directory, holding
admin-authored freeform CSS. The file's stylesheet MUST only be emitted when the `custom_css_enabled`
appconfig value is `'1'`. The default value MUST be `'0'`.

#### Scenario: Feature disabled by default on fresh install

@e2e exclude emission order is server-side, not DOM; proven by tests/Unit/Service/CssInjectionServiceTest.php::testCustomCssNotEmittedWhenDisabled and tests/Unit/Service/CustomCssServiceTest.php::testEnabledFlagLivesInAppConfig

- GIVEN the nldesign app is freshly installed
- WHEN Nextcloud loads the theming CSS
- THEN `custom_css_enabled` MUST resolve to `'0'`
- AND the `custom-css.css` stylesheet MUST NOT be emitted
- AND `custom-css.css` MUST NOT be required to exist on disk

#### Scenario: Enabling the feature emits the stylesheet

@e2e exclude emission order is server-side; proven by tests/Unit/Service/CssInjectionServiceTest.php::testCustomCssEmittedLastWhenEnabledAndPresent

- GIVEN an admin sets `custom_css_enabled` to `'1'`
- WHEN the browser next loads the CSS stack
- THEN `custom-css.css` MUST be emitted via the same stylesheet-loading mechanism as the other
  nldesign CSS layers
- AND the file MUST be created (with only the header comment) if it does not already exist

#### Scenario: Missing file with feature enabled does not break the stack

@e2e exclude proven by tests/Unit/Service/CssInjectionServiceTest.php::testCustomCssNotEmittedWhenEmpty and tests/Unit/Service/CustomCssServiceTest.php::testMissingFileReadsAsEmpty

- GIVEN `custom_css_enabled` is `'1'`
- AND `custom-css.css` does not exist on disk for any reason
- WHEN Nextcloud loads the CSS stack
- THEN the remaining CSS layers MUST apply normally
- AND no PHP error or unhandled exception MUST occur

### Requirement: CSS Stack Load Order
`custom-css.css` MUST be registered as the final CSS file in the nldesign app's CSS load order,
emitted immediately after `custom-overrides.css` and before every subsequent conditional stylesheet
(custom fonts, `hide-slogan`, `show-menu-labels`, the theme preview banner).

Load order (final, feature enabled):
```
... → element-overrides.css → custom-overrides.css → custom-css.css → (custom fonts, conditional stylesheets, preview banner)
```

#### Scenario: Freeform CSS wins over the token editor

@e2e exclude cascade position is the emission order; proven by tests/Unit/Service/CssInjectionServiceTest.php::testCustomCssEmittedLastWhenEnabledAndPresent

- GIVEN `custom-overrides.css` sets `--color-primary: #0000FF`
- AND `custom-css.css` contains `.app-navigation { border-inline-end-color: #0000FF; }`
- WHEN the browser resolves the navigation border color
- THEN the resolved value MUST come from `custom-css.css`'s rule, since it is the last stylesheet
  loaded

#### Scenario: Freeform CSS does not affect the preview banner

@e2e exclude structural: CssInjectionService::inject() runs the preview-banner layer after the override-styles layer that emits custom-css; no scenario-level UI

- GIVEN a theme preview is active for the requesting admin
- WHEN the CSS stack is loaded
- THEN `custom-css.css` MUST be emitted before the preview banner's own assets, so freeform admin
  rules never style preview-banner markup

### Requirement: Freeform CSS Is Sanitised Before Persisting
Every write to `custom-css.css` MUST pass through `CustomCssValidator` first. Validation MUST be
fail-closed and all-or-nothing: if any rule fails, the entire submission MUST be rejected with a
list of the specific rules violated, and no part of it may be written to disk.

#### Scenario: Oversized submission rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsOversizedPayload

- GIVEN an admin submits CSS text larger than 64 KB
- WHEN the save endpoint validates the submission
- THEN the submission MUST be rejected
- AND the error MUST state the size limit was exceeded
- AND `custom-css.css` MUST remain unchanged

#### Scenario: Remote import directives rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsImport and ::testRejectsCharset

- GIVEN the submitted CSS contains an `@import` or `@charset` rule anywhere in the text
- WHEN the submission is validated
- THEN it MUST be rejected with an error naming the disallowed at-rule
- AND `custom-css.css` MUST remain unchanged

#### Scenario: External url() rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsExternalUrl and ::testRejectsProtocolRelativeUrl

- GIVEN the submitted CSS contains `url(https://example.invalid/track.png)`, `url(//example.invalid/x)`,
  or any other absolute-scheme or protocol-relative URL
- WHEN the submission is validated
- THEN it MUST be rejected with an error naming the disallowed URL

#### Scenario: Same-origin and data URLs permitted

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testAcceptsRelativeUrl, ::testAcceptsDataUri and ::testAcceptsMultipleDataUrisAndRelativePaths

- GIVEN the submitted CSS contains `url(../img/logo.png)`, `url(/apps/nldesign/img/logo.png)`, or a
  `url(data:image/png;base64,...)` value
- WHEN the submission is validated
- THEN this rule MUST NOT reject the submission on that basis alone

#### Scenario: Script-execution vectors rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsScriptExecutionVectors

- GIVEN the submitted CSS contains `expression(`, `behavior:`, or `-moz-binding:`
- WHEN the submission is validated
- THEN it MUST be rejected with an error naming the disallowed construct

#### Scenario: Markup breakout strings rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsHtmlBreakout

- GIVEN the submitted CSS contains `</style` or `<script` (case-insensitive)
- WHEN the submission is validated
- THEN it MUST be rejected with an error naming the disallowed string

#### Scenario: Unbalanced braces rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsUnbalancedBraces and ::testBracesInStringsAndCommentsAreIgnored

- GIVEN the submitted CSS has a `{` with no matching `}` (or vice versa), outside of comments and
  string literals
- WHEN the submission is validated
- THEN it MUST be rejected with an error indicating unbalanced braces
- AND `custom-css.css` MUST remain unchanged

#### Scenario: Well-formed submission accepted

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testAcceptsOrdinaryCss

- GIVEN the admin submits CSS containing only ordinary selectors, standard properties, balanced
  braces, and no disallowed construct
- WHEN the submission is validated
- THEN it MUST pass validation
- AND it MUST be written to `custom-css.css` verbatim (after the standard generated-file header
  comment)

### Requirement: Reserved Dark-Mode Variables Cannot Be Set By Freeform CSS
Freeform CSS submissions MUST NOT declare `--color-main-background`, `--color-main-background-rgb`,
`--color-main-background-translucent`, `--color-main-background-blur`, `--color-background-plain`,
`--background-invert-if-dark`, or `--background-invert-if-bright`, regardless of which selector the
declaration appears in. This check MUST run over the entire submitted document, not only a `:root`
block.

#### Scenario: Reserved variable declared at :root is rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsReservedDarkModeVariables

- GIVEN the submitted CSS contains `:root { --color-main-background: #000000; }`
- WHEN the submission is validated
- THEN it MUST be rejected with an error naming `--color-main-background` as a reserved variable
- AND `custom-css.css` MUST remain unchanged

#### Scenario: Reserved variable declared on an arbitrary selector is also rejected

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testRejectsReservedVariableOutsideRoot

- GIVEN the submitted CSS contains `body.some-class { --background-invert-if-dark: 0; }`
- WHEN the submission is validated
- THEN it MUST be rejected with an error naming `--background-invert-if-dark` as a reserved variable

#### Scenario: Non-reserved custom properties are permitted

@e2e exclude validator rule; proven by tests/Unit/Service/CustomCssValidatorTest.php::testAcceptsOrdinaryCss

- GIVEN the submitted CSS declares a custom property not on the reserved list (for example
  `--my-team-accent: #003366;`)
- WHEN the submission is validated
- THEN this requirement MUST NOT reject the submission

### Requirement: Freeform CSS Endpoints Require Admin Authorization And Are Audit-Logged
Every endpoint that reads or writes `custom-css.css` or the `custom_css_enabled` flag MUST carry
`#[AuthorizedAdminSetting(Admin::class)]`. Every successful write (content save or enabled-flag
toggle) MUST be recorded via the existing theming audit trail before the response is returned.

#### Scenario: Unauthenticated or non-admin request is rejected

@e2e exclude enforced by Nextcloud middleware from the attribute; proven by tests/Unit/Controller/CustomCssControllerAuditTest.php::testEveryEndpointIsAdminOnly

- GIVEN a request to the custom CSS save endpoint is made without a valid admin (or delegated admin)
  session
- WHEN the request reaches the controller
- THEN Nextcloud's authorization middleware MUST reject it before any validation or file write occurs

#### Scenario: Delegated admin can reach the endpoint

@e2e exclude delegation is granted by the AuthorizedAdminSetting attribute; proven by tests/Unit/Controller/CustomCssControllerAuditTest.php::testEveryEndpointIsAdminOnly and ::testSuccessfulWriteIsAudited

- GIVEN a user has been granted delegated access to the nldesign admin settings section (not full
  instance-admin rights)
- WHEN that user submits new freeform CSS
- THEN the request MUST be accepted by the authorization layer (matching every other
  `Admin::class`-gated endpoint in this app)
- AND the resulting audit entry MUST record that user's uid

#### Scenario: Successful save is audit logged

@e2e exclude proven by tests/Unit/Controller/CustomCssControllerAuditTest.php::testSuccessfulWriteIsAudited

- GIVEN an admin submits CSS that passes validation
- WHEN the write succeeds
- THEN a new audit entry MUST be appended recording the action, the acting user, and enough
  information to identify the change (for example a content hash or diff), before the endpoint
  returns a success response

#### Scenario: Enabling or disabling the feature is audit logged

@e2e exclude the flag travels in the same save and its new state is in the custom_css_written entry; proven by tests/Unit/Controller/CustomCssControllerAuditTest.php::testSuccessfulWriteIsAudited

- GIVEN an admin changes `custom_css_enabled` from `'0'` to `'1'` or back
- WHEN the change is saved
- THEN a new audit entry MUST be appended recording the toggle and the acting user

### Requirement: Read/Write PHP Endpoint
The backend MUST expose a PHP service that reads the current `custom-css.css` content and enabled
state, and writes a new version atomically after validation succeeds. Direct file manipulation from
the admin settings JavaScript MUST NOT be used.

#### Scenario: Read current custom CSS

@e2e exclude proven by tests/Unit/Controller/CustomCssControllerAuditTest.php::testReadReturnsTheStoredCssAndState and tests/Unit/Service/CustomCssServiceTest.php::testSavedCssReadsBackExactlyAsWritten

- GIVEN `custom-css.css` exists with admin-authored rules
- WHEN the admin settings panel loads
- THEN a GET request to the custom CSS endpoint MUST return the raw CSS content and the current
  `custom_css_enabled` state as JSON

#### Scenario: Write new custom CSS

@e2e exclude proven by tests/Unit/Service/CustomCssServiceTest.php::testSavedCssReadsBackExactlyAsWritten and ::testSavingTwiceKeepsOneHeader

- GIVEN an admin submits new CSS text that passes `CustomCssValidator`
- WHEN a POST request is made to the custom CSS save endpoint
- THEN the backend MUST write the content to `custom-css.css` atomically (write to a temp file, then
  rename)
- AND it MUST return HTTP 200 with the stored size in bytes and the enabled state
- AND reading it back MUST return exactly the submitted text, without the comment the file
  carries above it

#### Scenario: Write fails due to filesystem permissions

@e2e exclude proven by tests/Unit/Service/CustomCssServiceTest.php::testUnwritableDirectoryThrowsAndKeepsTheOldFile and tests/Unit/Controller/CustomCssControllerAuditTest.php::testUnwritableFileIsAServerError

- GIVEN the CSS directory is not writable by the web server process
- WHEN a validated save is attempted
- THEN the server MUST return HTTP 500
- AND the error response MUST include a message indicating the file could not be written
- AND the existing `custom-css.css` MUST remain unchanged

#### Scenario: Validation failure returns a structured error, nothing is written

@e2e exclude proven by tests/Unit/Service/CustomCssServiceTest.php::testValidationFailureWritesNothing and tests/Unit/Controller/CustomCssControllerAuditTest.php::testRejectedSubmissionIsAudited

- GIVEN an admin submits CSS that fails one or more `CustomCssValidator` rules
- WHEN a POST request is made to the custom CSS save endpoint
- THEN the server MUST return HTTP 422 with the list of failed rules
- AND the existing `custom-css.css` MUST remain unchanged

### Requirement: No Database Storage
Neither the freeform CSS content nor the `custom_css_enabled` flag MUST be stored in a database
table. The CSS file is the sole content-persistence mechanism; the flag is stored via Nextcloud's
`IConfig` appconfig store, consistent with every other nldesign toggle.

#### Scenario: Enabled flag stored via appconfig, not a database table

@e2e exclude proven by tests/Unit/Service/CustomCssServiceTest.php::testEnabledFlagLivesInAppConfig

- GIVEN an admin enables the feature
- WHEN the setting is persisted
- THEN it MUST be written via `IConfig::setAppValue('nldesign', 'custom_css_enabled', '1')`
- AND no new database table or column MUST be introduced

#### Scenario: Custom CSS survives app reinstall if the CSS directory is preserved

@e2e exclude a deployment property of the app directory, not app behaviour; nothing to drive from a page

- GIVEN `custom-css.css` exists in the app's CSS directory
- WHEN the nldesign app is disabled and re-enabled
- THEN `custom-css.css` MUST still be emitted after re-enable (subject to `custom_css_enabled`
  remaining `'1'`)
- AND no database query MUST be required to restore its content
