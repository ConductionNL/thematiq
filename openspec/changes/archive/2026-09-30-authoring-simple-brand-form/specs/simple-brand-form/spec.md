# Spec delta: simple brand form (simple-brand-form)

A new capability. An administrator creates a complete house style from a name, a primary colour, a background colour and an optional logo.

## ADDED Requirements

### Requirement: An administrator creates a house style from a short form

The Custom token sets block on Settings > Administration > Theming MUST offer a form with a name, a primary colour, a background colour and an optional logo. Saving it MUST store a custom token set through the same store path as an upload, so it appears in the Design token set dropdown and can be edited, exported, previewed and mapped to groups like any other custom set.

#### Scenario: An administrator creates a house style from a red and a white

- GIVEN an administrator on Settings > Administration > Theming
- WHEN they enter the name "Gemeente Voorbeeld", primary `#c8102e`, background `#ffffff`, a logo, and save
- THEN the Design token set dropdown MUST offer "Gemeente Voorbeeld"
- AND selecting it MUST render primary buttons in `#c8102e` with readable text
- AND the theming-sync dialog MUST offer the logo and `#c8102e` for Nextcloud core theming

#### Scenario: The name is already taken

- GIVEN a custom token set named "Gemeente Voorbeeld" exists
- WHEN an administrator saves the form with the same name
- THEN nothing MUST be stored
- AND the form MUST say the name is taken

### Requirement: The derived set is complete

The generated set MUST declare every token the vocabulary audit requires (`TokenSetVocabularyAuditService::REQUIRED_TOKENS`). Tokens the form does not ask for MUST take the defaults layer values. The derivation MUST be the same in the browser preview and on the server.

#### Scenario: The audit finds nothing missing

- GIVEN a set created from the form
- WHEN `npm run audit:token-sets` runs over it
- THEN it MUST report no missing required token

#### Scenario: The preview shows what will be stored

- GIVEN an administrator filling in the form
- WHEN they change the primary colour
- THEN the preview MUST repaint with the hover and pressed shades the server will store

### Requirement: Text on primary is chosen for contrast

The form MUST choose black or white text on the primary colour, whichever reaches the higher contrast, and MUST show the contrast of text on primary against 4.5:1 and of primary on background against 3:1 before saving. A result below either threshold MUST be shown as a warning and MUST NOT block saving.

#### Scenario: A light brand colour gets dark text

- GIVEN an administrator enters primary `#ffd200`
- WHEN the form computes the text colour
- THEN it MUST choose black text
- AND it MUST show a contrast of at least 4.5:1

#### Scenario: A mid-tone colour is warned about, not refused

- GIVEN an administrator enters a primary for which neither black nor white reaches 4.5:1
- WHEN they save
- THEN the set MUST be stored
- AND the form MUST show the contrast warning with the ratio and the 4.5:1 threshold

### Requirement: The form endpoint is admin-only

`POST /settings/tokensets/from-colours` MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]` and MUST refuse a colour that is not a hex colour with 400.

#### Scenario: A non-admin cannot create a set

- GIVEN a signed-in user who is not an administrator
- WHEN they call `POST /apps/thematiq/settings/tokensets/from-colours`
- THEN the response MUST be 403 and nothing MUST be stored
