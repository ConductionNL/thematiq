# Spec: Workplace layout

## ADDED Requirements

### Requirement: The header style is a layout option
The app MUST store a header style in the app config under `header_style`: `default`, `workplace`
or the empty value for "follow the theme". `POST /settings/layout` MUST store it with the other
layout options, MUST refuse an unknown value with HTTP 400 and store nothing, and MUST audit a
change. A token set's `layout` block MAY name it; with nothing stored the set's value applies, and
a set that names none resolves to `default`. `css/header-workplace.css` MUST be emitted only while
the workplace layout resolves to `light` and the header style to `workplace`. The configuration
bundle MUST carry it as `config.layoutOptions.headerStyle`.

#### Scenario: Zuiddrecht wears the workplace bar
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php reads the shipped manifest
- GIVEN no stored choice and the active set `zuiddrecht`
- WHEN the stylesheets are resolved
- THEN `header-workplace` MUST be among them, after `workplace-layout`

#### Scenario: The workplace bar needs the light layout
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php and CssInjectionServiceTest.php
- GIVEN the header style `workplace`
- WHEN the workplace layout resolves to `default`
- THEN `header-workplace` MUST NOT be emitted

#### Scenario: Every other shipped set keeps Nextcloud's bar
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/LayoutOptionsServiceTest.php walks every shipped set
- GIVEN no stored choice
- WHEN the header style is resolved for every shipped set except `zuiddrecht`
- THEN it MUST be `default`

### Requirement: The workplace header shows the name and the role
While `header-workplace` is emitted for a signed-in person, the page MUST carry the initial state
`thematiq/header-user` with the person's display name and the value of their profile's `role`
property (empty when none), and MUST load `js/header-user.js`. The script MUST write both as text,
MUST mark the label `aria-hidden`, MUST leave Nextcloud's menu button and its name in place, and
MUST show the name alone when the role is empty. With nobody signed in, nothing MUST be emitted. A
failure MUST be logged and MUST leave the bar with Nextcloud's avatar.

#### Scenario: The board's person
@e2e exclude Live-checked on :8080 with a role set through `occ user:profile`; unit: PHPUnit HeaderUserServiceTest and vitest tests/vitest/headerUser.spec.js
- GIVEN Pieter Jansen, whose profile role is "Woo-coördinator"
- WHEN a page renders with the workplace bar
- THEN the bar MUST show "PJ", "Pieter Jansen" and "Woo-coördinator"

#### Scenario: No role
@e2e exclude Unit: vitest tests/vitest/headerUser.spec.js
- GIVEN a person without a profile role
- WHEN the bar renders
- THEN it MUST show the initials and the name only
