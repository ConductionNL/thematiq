# Spec delta: own tokens (authoring-token-lifecycle)

An administrator adds a token of their own from the token editor, without a code change and
without uploading a token set.

## ADDED Requirements

### Requirement: An administrator adds an own token from the token editor

The token editor MUST show a section "Your own tokens" below its tabs, with an "Add a token"
action. Adding a token MUST ask for a name, a label, a type, a value and, for a colour, an optional
dark value. The name MUST be `--nldesign-org-` followed by a slug of lowercase letters, digits and
single dashes, at most 48 characters. `POST /apps/thematiq/settings/tokens/own` MUST store it in
appconfig `own_tokens` and MUST refuse with 400 a name that breaks the rule, a name that already
exists, or a value that fails its type. The endpoint MUST be admin-only.

#### Scenario: An administrator adds a brand accent colour
- GIVEN an administrator on Settings > Administration > Theming, in the token editor
- WHEN the administrator adds a token named `brand-accent`, label "Brand accent", type colour,
  value `#e17000` and dark value `#ff9a3c`
- THEN the section MUST list `--nldesign-org-brand-accent` with its label and both values
- AND a page of any app MUST resolve `var(--nldesign-org-brand-accent)` to `#e17000` in the light theme

#### Scenario: A name outside the rule is refused
@e2e exclude API validation branch, covered by PHPUnit on OwnTokenController and the Newman collection
- GIVEN an administrator
- WHEN they post an own token named `Brand_Accent`
- THEN the response MUST be 400 naming the name rule
- AND nothing MUST be stored

#### Scenario: A duplicate name is refused
@e2e exclude API validation branch, covered by PHPUnit on OwnTokenService
- GIVEN the own token `--nldesign-org-brand-accent` exists
- WHEN an administrator adds `brand-accent` again
- THEN the response MUST be 400 stating the token exists

#### Scenario: A non-administrator cannot add a token
@e2e exclude Auth posture, covered by the Newman collection
- GIVEN a logged-in user who is not an administrator
- WHEN they post to `/apps/thematiq/settings/tokens/own`
- THEN the response MUST be 403

### Requirement: Own tokens are served in both themes

Own tokens MUST be written into `custom-overrides.css` after the registry overrides, with light
values in `:root` and dark values in the dark scopes the file carries. They MUST NOT carry
`!important`. A colour without a dark value MUST use its light value in both themes.

#### Scenario: A user in the dark theme gets the dark value
- GIVEN the own token `--nldesign-org-brand-accent` with light `#e17000` and dark `#ff9a3c`
- WHEN a user who chose the dark theme loads Files
- THEN `var(--nldesign-org-brand-accent)` MUST resolve to `#ff9a3c`

#### Scenario: Custom CSS can use an own token at once
- GIVEN the own token `--nldesign-org-brand-accent`
- WHEN the administrator adds `.header-menu { border-color: var(--nldesign-org-brand-accent); }` to the freeform custom CSS and saves
- THEN the header menu border MUST render in `#e17000`

### Requirement: An administrator edits and removes own tokens

Each own token row MUST offer "Edit" and "Remove". Editing MUST keep the name and change label,
type, value, dark value or description. Removing MUST ask for confirmation, and MUST name the
deprecation, if one exists, in that question. Both MUST go through admin-only endpoints and write
an `own_token_changed` audit entry.

#### Scenario: An administrator removes a token after its removal date
- GIVEN `--nldesign-org-old-accent` is deprecated with removal date 2026-10-01, and today is later
- WHEN the administrator clicks "Remove" and confirms
- THEN the token MUST disappear from `custom-overrides.css`
- AND its deprecation MUST be kept with the state `removed`, so consumers can still read what happened

### Requirement: Own tokens travel with the configuration bundle

The configuration bundle export MUST carry own tokens under `ownTokens`. Importing a bundle with
`ownTokens` MUST replace the target's own tokens. Importing a bundle without it MUST leave them
unchanged.

#### Scenario: Own tokens move from test to production
@e2e exclude Bundle content, covered by PHPUnit on ConfigBundleService
- GIVEN a test instance with two own tokens
- WHEN an administrator exports the configuration and imports it on production
- THEN production MUST have the same two own tokens with the same values
