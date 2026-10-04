# Spec: Zuiddrecht token set

Gemeente Zuiddrecht is a demo municipality. Its token set themes Nextcloud as a light workplace.

## ADDED Requirements

### Requirement: The Set Declares The Zuiddrecht Palette
`css/tokens/zuiddrecht.css` MUST be one flat `:root` block on the `--nldesign-*` vocabulary. The
interactive colour MUST be blue #3669A5 with hover #234A78. Red #CC0000 MUST be the accent and
MUST NOT be the primary colour. The radius scale MUST be Nextcloud's: 8px for controls and 12px
for containers. `token-sets.json` MUST name the set `Gemeente Zuiddrecht` on the `nldesign` design
system with primary colour #3669A5, background colour #F5F6F8, a logo and a dark logo.

#### Scenario: The vocabulary audit passes
@e2e exclude Static file check: PHPUnit tests/Unit/TokenSetVocabularyTest.php and `npm run audit:token-sets:check` audit every shipped set
- GIVEN the shipped token sets
- WHEN the vocabulary audit runs
- THEN `zuiddrecht` MUST be complete: no missing required token, no name nothing reads, and a primary colour equal to the manifest's

#### Scenario: The set is selectable
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/TokenSetServiceSelectableTest.php asserts the picker list
- GIVEN a fresh instance on the stock set
- WHEN the admin panel lists the selectable token sets
- THEN the list MUST include `zuiddrecht`

### Requirement: Every Text Pair Reaches AA
Every text and background pair the set names MUST reach 4.5:1, in light and in both dark scopes.
A control border MUST reach 3:1 against the control's fill.

#### Scenario: Light pairs
@e2e exclude Computed from the token files: tests/vitest/zuiddrechtTokenSet.spec.js resolves the cascade and computes each ratio
- GIVEN the light token file
- WHEN each named pair is computed
- THEN white on the primary, the primary on white and on the workspace, the text and the muted text on white and on the workspace, the navigation label on its wash, the badge number on the badge and each status pill label on its tint MUST reach 4.5:1

#### Scenario: Dark pairs
@e2e exclude Computed from the token files: tests/vitest/zuiddrechtTokenSet.spec.js reads the generated dark file and the set's overrides
- GIVEN the generated dark file and `css/token-overrides/zuiddrecht.css`
- WHEN each named pair is computed against the dark main background and the dark surface
- THEN each MUST reach 4.5:1

### Requirement: The Dark Workspace Sits Below The Cards
In the dark the page background MUST be dark, and the content surface MUST NOT be lighter than
the cards on it. `css/token-overrides/zuiddrecht.css` MUST set both for the two dark scopes, and
MUST leave the light scheme alone.

#### Scenario: The dark page is dark
@e2e exclude Static file check: tests/vitest/zuiddrechtTokenSet.spec.js reads the overrides file
- GIVEN the set's overrides file
- WHEN its rules are read
- THEN every rule MUST sit in a dark scope, and MUST set the page background, its text colour and the content surface

### Requirement: The Bundled Font Carries Four Weights
Fira Sans MUST be served in the weights 400, 500, 600 and 700, normal and italic, from the app's
own files, with the SIL Open Font License beside them.

#### Scenario: A weight the design uses is served
@e2e exclude Static file check: tests/vitest/fontLicences.spec.js and tests/vitest/zuiddrechtTokenSet.spec.js read the stylesheets and the files
- GIVEN `css/systems/nldesign/fonts.css`
- WHEN its `@font-face` rules are read
- THEN each of the four weights MUST have a rule whose file exists
