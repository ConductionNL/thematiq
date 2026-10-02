# Spec delta: own component preview (authoring-own-markup-preview)

An administrator who builds components pastes their markup and CSS into the playground and sees
them in the house style, with unsaved token edits, before anything is published.

## ADDED Requirements

### Requirement: The playground previews a builder's own component

Every tab's chip row in the component playground MUST end with a chip "Your component". Choosing it
MUST open a stage with an HTML field, a CSS field, a light and dark switch and a preview frame. The
playground MUST keep working when this stage cannot build, as the playground's own spec requires
for the rest of the instrument.

#### Scenario: An administrator previews a card they are building
- GIVEN an administrator on Settings > Administration > Theming, in the component playground
- WHEN the administrator chooses "Your component" and pastes
  `<div class="card"><h2>Afval</h2><p>Ophaaldagen</p></div>` as HTML and
  `.card { background: var(--nldesign-color-primary); color: var(--nldesign-color-primary-text); padding: 16px; }` as CSS
- THEN the frame MUST show the card with the active set's primary colour and primary text colour

#### Scenario: A broken stage does not break the playground
@e2e exclude Failure injection, covered by vitest on playground.js with a throwing stage builder
- GIVEN the own component stage throws while it builds
- WHEN the theming panel renders
- THEN the shipped component chips and the token editor MUST still work

### Requirement: Pasted markup renders in a sandboxed frame without scripts

The preview frame MUST be an `iframe` with `srcdoc` and exactly `sandbox="allow-same-origin"`. It
MUST NOT carry `allow-scripts`, `allow-forms`, `allow-popups`, `allow-modals` or
`allow-top-navigation`. The `srcdoc` document MUST start with a Content Security Policy of
`default-src 'none'; style-src 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:`.

#### Scenario: A pasted script does not run
- GIVEN an administrator pastes `<button onclick="alert(1)">Test</button><script>alert(2)</script>`
- WHEN the frame renders
- THEN no dialog MUST appear
- AND the stage MUST report that one script and one event handler were removed

#### Scenario: A pasted image cannot call home
- GIVEN an administrator pastes `<img src="https://example.org/pixel.gif">`
- WHEN the frame renders
- THEN no request to `example.org` MUST be made
- AND the stage MUST report that one external address was removed

#### Scenario: The sandbox attribute cannot drift
@e2e exclude Attribute pin, covered by vitest on playground.js
- GIVEN the code that builds the frame
- WHEN the unit test builds it
- THEN the `sandbox` attribute MUST equal `allow-same-origin`
- AND the test MUST fail if `allow-scripts` is present

### Requirement: Markup is cleaned against an allowlist every time it renders

Before markup reaches the frame, `js/lib/markupSanitizer.js` MUST remove every element and attribute
outside its allowlist, every `on*` attribute, every `href` other than a `#fragment`, every `src`
other than `data:image/...`, and every `url()` other than `data:`. It MUST return what it removed,
and the stage MUST show that list. A saved component MUST be cleaned again each time it renders.

#### Scenario: A javascript link is removed
@e2e exclude Sanitiser rules, covered by vitest on markupSanitizer.js
- GIVEN the markup `<a href="javascript:alert(1)">Open</a>`
- WHEN it is cleaned
- THEN the result MUST be `<a>Open</a>`
- AND the removal list MUST name the `href`

#### Scenario: Allowed structure survives
@e2e exclude Sanitiser rules, covered by vitest on markupSanitizer.js
- GIVEN the markup `<nav aria-label="Menu"><ul><li><a href="#start" class="link">Start</a></li></ul></nav>`
- WHEN it is cleaned
- THEN the result MUST be identical to the input
- AND the removal list MUST be empty

### Requirement: The frame wears the house style, live

For every `var(--name)` the builder's CSS or `style` attributes read, the frame's root MUST get the
value the playground's preview container has for that name at that moment, unsaved edits included.
Editing a token in the playground MUST repaint the frame without rebuilding it. The house style
fonts MUST load in the frame. The token list beside the stage MUST filter to the names the code
reads. A name the editor can write MUST show its editor row. Any other name MUST show its value
and the note "Read-only here: this comes from the token set".

#### Scenario: An unsaved colour edit repaints the frame
- GIVEN the card from the first scenario is in the frame
- WHEN the administrator changes the primary colour in the filtered token list to `#c00000` without saving
- THEN the card's background in the frame MUST become `#c00000`
- AND no other user MUST see the change

#### Scenario: The token list follows the pasted code
- GIVEN the pasted CSS reads `--nldesign-color-primary` and `--nldesign-org-brand-accent`
- WHEN the stage renders
- THEN the token list MUST show exactly those two names
- AND a name the editor cannot write MUST carry the read-only note

### Requirement: The frame can show the dark theme

The light and dark switch MUST set the frame to the dark theme by applying the set's dark
`--nldesign-*` values, and the editor's dark values where they exist. It MUST state that
Nextcloud's own variables keep the administrator's current theme.

#### Scenario: A builder checks the card in dark mode
- GIVEN the card in the frame and a set with a generated dark variant
- WHEN the administrator switches the frame to dark
- THEN the card MUST use the dark value of `--nldesign-color-primary` from `css/tokens/dark/{set}.css`
- AND the note about Nextcloud's own variables MUST be visible

### Requirement: An administrator saves own components

`POST /apps/thematiq/settings/playground/components` MUST store a component by name in the app's
data folder, at most 20 components and at most 64 KB of HTML and CSS together per component, and
MUST refuse more with 400. `GET` MUST list them and `DELETE /apps/thematiq/settings/playground/components/{slug}`
MUST remove one. All three MUST be admin-only. A saved component MUST be addressable as
`#preview={tab}/own-{slug}`, and a slug that no longer exists MUST be ignored. Saving MUST NOT
change what any user sees and MUST NOT write an audit entry.

#### Scenario: An administrator reopens a saved card from a link
- GIVEN the administrator saved the card as "Afvalkaart"
- WHEN the administrator opens `/settings/admin/theming#preview=content/own-afvalkaart`
- THEN the own component stage MUST open with the saved HTML and CSS

#### Scenario: A component over the size limit is refused
@e2e exclude API validation branch, covered by PHPUnit on OwnComponentController and the Newman collection
- GIVEN an administrator
- WHEN they save a component whose HTML and CSS together exceed 64 KB
- THEN the response MUST be 400 naming the limit
- AND nothing MUST be stored

#### Scenario: A non-administrator cannot save or read components
@e2e exclude Auth posture, covered by the Newman collection
- GIVEN a logged-in user who is not an administrator
- WHEN they call `GET /apps/thematiq/settings/playground/components`
- THEN the response MUST be 403
