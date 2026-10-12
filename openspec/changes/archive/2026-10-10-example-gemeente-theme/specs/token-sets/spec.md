# Spec delta: Token Sets (example-gemeente-theme)

@e2e exclude token-file and manifest invariants, asserted by vitest and PHPUnit over the
generated files; the one visible check (the set in the admin dropdown) is covered by the
existing token-set-dropdown e2e, which iterates every manifest entry.

## ADDED Requirements

### Requirement: An example municipality set ships beside the example school sets
The app MUST ship a token set with id `example-gemeente` and name "(EXAMPLE) Gemeente", built
the way the four example school sets are built: a brand source file
`scripts/brands/example-gemeente.json`, a generated `css/tokens/example-gemeente.css` with the
same four sections (palette, own component mapping, shared role layer, `--nldesign-*` layer),
a generated dark variant, a manifest entry in `token-sets.json` and a logo at
`img/logos/example-gemeente.svg`. Its description MUST say it is fictional and not a real
organisation.

#### Scenario: The set is listed and selectable
- **GIVEN** the app installed from this change
- **WHEN** an admin opens the token set dropdown
- **THEN** "(EXAMPLE) Gemeente" MUST be listed beside the four example school sets
- **AND** applying it MUST set the primary colour to `#12506B` and the logo to `img/logos/example-gemeente.svg`

#### Scenario: The generated file is reproducible
- **GIVEN** `scripts/brands/example-gemeente.json` unchanged
- **WHEN** `scripts/generate-brand-set.mjs` runs twice
- **THEN** both runs MUST write a byte-identical `css/tokens/example-gemeente.css`
- **AND** the file MUST hold exactly one flat `:root` block

### Requirement: The example municipality palette is the palette of the approved mockups
The set MUST carry the values the approved mockups declare in `portal.css`: primary `#12506B`,
primary hover `#0B3648`, primary light `#E7F1F5`, accent `#8F4A00`, soft surface `#F5F7F8`,
text `#1B1A18`, muted text `#55514C`, success `#17603A` on `#E6F3EB`, warning `#7A4800` on
`#FCF1D8`, error `#9C231C` on `#FCEBE9` and info `#1D4F91` on `#E8F0FB`. A ramp step the
mockups leave open MUST be chosen so that every step a component can put text on, or under
white text, reaches 4.5:1 against white, and the brand file MUST record the source of each
step.

#### Scenario: The set passes the contrast audit
- **GIVEN** the generated `example-gemeente` set
- **WHEN** the shipped token set contrast audit runs
- **THEN** primary against primary text MUST be `AA` (8.80:1)
- **AND** primary against the background MUST be `AA`
- **AND** the set MUST appear in `docs/reference/contrast-report.md`

#### Scenario: A ramp step without a source is refused
- **GIVEN** a step in the `ramp` of `scripts/brands/example-gemeente.json` without a `from`
- **WHEN** the generator runs
- **THEN** the generator MUST refuse to write the file and MUST name the step

### Requirement: The example municipality set names a font the app ships
The set MUST name Source Sans 3 as its font family, and the app MUST bundle Source Sans 3 in
weights 400, 600 and 700 (latin subset, woff2) under the SIL Open Font License 1.1, with the
licence recorded in `REUSE.toml`. The family stack MUST end in a generic fallback.

#### Scenario: The font loads from the app
- **GIVEN** the `example-gemeente` set active
- **WHEN** a page renders body text
- **THEN** the computed font family MUST resolve to Source Sans 3 from the app's own `css/fonts/` files
- **AND** no request MUST go to a third-party font host
