# Spec: Workplace layout

## ADDED Requirements

### Requirement: A token set may carry the not-found copy
A token set's manifest entry MAY carry a `not_found` block with the text keys `eyebrow`, `title`,
`text`, `links_heading` and `hint`, and `links`, a list of `{label, url}`. Every text MUST follow
the login copy's rules: one string or a map from a language code to a string, empty or longer
than 300 characters dropped, and a language the map does not name keeping Nextcloud's words. A
link MUST be dropped without a label, or when its address is not an http(s) address or a path on
this server. At most six links MUST be shown. An administrator MAY store the same shape in the app
config key `not_found_copy`; its keys MUST win over the set's, key by key.

#### Scenario: Zuiddrecht ships the board's copy
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/NotFoundCopyServiceTest.php reads the shipped manifest
- GIVEN the token set `zuiddrecht` and a Dutch request
- WHEN the not-found copy is resolved
- THEN the eyebrow MUST be "Foutcode 404" and the title "Pagina niet gevonden"
- AND the links MUST be "De homepage" to `/`

#### Scenario: A link the page may not show is dropped
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/NotFoundCopyServiceTest.php
- GIVEN an override with links to `javascript:alert(1)`, to `//elders.example/` and one without a label
- WHEN the copy is resolved
- THEN none of those links MUST be returned

### Requirement: The light workplace draws the not-found page of the board
While the workplace layout resolves to `light`, Nextcloud's 404 page MUST NOT show its empty icon
block. For the core `404` template on a themed context, the page MUST carry the initial state
`thematiq/not-found-copy` and MUST load `js/not-found.js`; on any other template, context or
layout it MUST NOT. The script MUST write text only. It MUST put the eyebrow above the title, write
the title and the text, and add the links heading, the links (each with a chevron hidden from
assistive technology) and the hint. With at least one link, Nextcloud's "Back to …" button MUST
give way to them; without links it MUST stay. A failure MUST be logged and MUST leave Nextcloud's page.

#### Scenario: Zuiddrecht's not-found page
@e2e exclude Live-checked on :8080 against the NietGevonden board with screens-diff.mjs --no-login; unit: vitest tests/vitest/notFound.spec.js
- GIVEN the token set `zuiddrecht` and a Dutch browser
- WHEN a page that does not exist is opened
- THEN the card MUST show "Foutcode 404", "Pagina niet gevonden", the explanation, "Of ga verder naar" and "De homepage"
- AND no empty block MUST stand above the title

#### Scenario: Only Nextcloud's own 404 page asks for the copy
@e2e exclude Pure service logic: PHPUnit tests/Unit/Listener/ThemeInjectionListenerTest.php
- GIVEN the light workplace
- WHEN another core guest page, an app template named `404`, or an unthemed 404 page renders
- THEN the not-found copy MUST NOT be asked

#### Scenario: Without links the back button stays
@e2e exclude Unit: vitest tests/vitest/notFound.spec.js
- GIVEN a copy without links
- WHEN the page renders
- THEN Nextcloud's "Back to …" button MUST stay
