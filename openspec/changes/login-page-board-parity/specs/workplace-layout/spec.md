# Spec: Workplace layout

## ADDED Requirements

### Requirement: A token set may carry the login copy
A token set's manifest entry MAY carry a `login` block with the keys `title`, `subtitle`,
`sso_label`, `username_label`, `divider`, `footer`, `footer_hint`, `footer_link_label` and
`footer_link_url`. Each value MUST be either one string or a map from a language code to a string.
For a map, the request's language MUST be used, else its primary subtag; a language the map does
not name MUST leave Nextcloud's own words. An administrator MAY store the same shape in the app
config key `login_copy`; its keys MUST win over the set's, key by key. A value that is not text,
is empty, is longer than 300 characters, or (for `footer_link_url`) is not an http(s) address or a
path on this server MUST be dropped.

#### Scenario: Zuiddrecht ships the board's copy
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LoginCopyServiceTest.php reads the shipped manifest
- GIVEN the token set `zuiddrecht` and a Dutch request
- WHEN the login copy is resolved
- THEN the title MUST be "Inloggen bij de werkplek"
- AND the subtitle MUST be "Voor medewerkers van Gemeente Zuiddrecht."
- AND the SSO label MUST be "Inloggen met uw gemeenteaccount"
- AND the footer MUST be "Gemeente Zuiddrecht · werkplek op Nextcloud"

#### Scenario: A language the copy does not name keeps Nextcloud's words
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LoginCopyServiceTest.php
- GIVEN a title given in `nl` and `en` only
- WHEN the copy is resolved for a German request
- THEN no title MUST be returned

#### Scenario: The administrator's override wins key by key
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LoginCopyServiceTest.php
- GIVEN a set with a title and a subtitle, and an override with a title and a footer link
- WHEN the copy is resolved
- THEN the title and the link MUST come from the override and the subtitle from the set

#### Scenario: A link off this server without a scheme is dropped
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LoginCopyServiceTest.php
- GIVEN `footer_link_url` is `javascript:alert(1)` or `//elders.example/`
- WHEN the copy is resolved
- THEN the link MUST be dropped

### Requirement: The light workplace draws the login card of the board
On the login page, while the workplace layout resolves to `light`, the page MUST carry the initial
state `thematiq/login-copy` with the resolved copy and a `divider` (the set's, else this app's
translation of "or"), and MUST load `js/login-copy.js`. On any other layout neither MUST be
emitted. The script MUST write text only, never markup. It MUST put the title (the copy's, else
Nextcloud's headline) and the subtitle at the top of the card. It MUST move the alternative logins
above the form in the DOM, followed by the divider, and MUST mark the card so the password button
is drawn as the secondary action. With exactly one alternative login it MUST label it with the SSO
label. It MUST label the username field with the username label, and MUST replace the footer's
name and slogan with the footer line while keeping Nextcloud's legal links. A footer link MUST be
shown only with both a label and an accepted address. A failure MUST be logged and MUST leave
Nextcloud's login page.

#### Scenario: Zuiddrecht's login page
@e2e exclude Live-checked on :8080 with the pixel diff against the NcLogin board (screens-diff.mjs --no-login); unit: PHPUnit LoginCopyServiceTest and CssInjectionServiceTest
- GIVEN the token set `zuiddrecht` and a Dutch browser
- WHEN the login page renders
- THEN the card MUST show "Inloggen bij de werkplek" and "Voor medewerkers van Gemeente Zuiddrecht."
- AND the field label MUST read "Gebruikersnaam of e-mail"
- AND the footer MUST read "Gemeente Zuiddrecht · werkplek op Nextcloud"

#### Scenario: An SSO button comes first
@e2e exclude Needs a login app with a provider on the instance; unit: vitest tests/vitest/loginCopy.spec.js
- GIVEN a login app registers one alternative login
- WHEN the login page renders
- THEN the button MUST come before the form in the DOM, read "Inloggen met uw gemeenteaccount", and be followed by "of"
- AND the password button MUST be drawn outlined

#### Scenario: Only the login page asks for the copy
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/CssInjectionServiceTest.php
- GIVEN the light workplace
- WHEN a user page or a guest page renders
- THEN the login copy MUST NOT be asked

#### Scenario: The default layout keeps Nextcloud's login page
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LoginCopyServiceTest.php
- GIVEN a set on the default layout that carries a `login` block
- WHEN the login page renders
- THEN no login copy state and no script MUST be emitted
