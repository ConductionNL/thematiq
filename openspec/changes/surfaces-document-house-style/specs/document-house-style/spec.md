# Spec delta: document house style (document-house-style)

A new capability. Thematiq publishes the house style values that generated documents need, for the fleet apps that generate them.

## ADDED Requirements

### Requirement: Thematiq publishes a document house style profile

The app MUST provide a document house style profile with the organisation name, a logo, an optional cover image, the primary, primary text, text, background and accent colours, the heading and body fonts with a font file URL for custom fonts, and footer lines with the accessibility and privacy links. Colours MUST come from the token set that applies to the requesting user, so a group-mapped house style yields its own profile.

#### Scenario: A letter for a group-mapped municipality gets its own colours

- GIVEN group `gemeente-bussum` mapped to set `bussum` and the instance default `rijkshuisstijl`
- WHEN a member of `gemeente-bussum` requests `GET /apps/thematiq/api/document-style`
- THEN the profile MUST carry the Bussum primary colour and token set id `bussum`

#### Scenario: A set without fonts reports system fonts

- GIVEN an active set with no custom fonts uploaded
- WHEN a signed-in user requests the profile
- THEN the font entries MUST name the family and carry no font file URL

### Requirement: Fleet apps read the profile in-process or over HTTP

The profile MUST be available to other apps on the same server through `OCA\Thematiq\Service\DocumentStyleService`, and to signed-in users through `GET /apps/thematiq/api/document-style` (`#[NoAdminRequired]`). Both MUST return the same values. The endpoint MUST NOT answer without a session.

#### Scenario: A document app renders a letter in the house style

- GIVEN filinq resolves `DocumentStyleService` for the signed-in user
- WHEN it renders a letter
- THEN the logo, primary colour, fonts and footer lines it uses MUST equal the profile's values

#### Scenario: No session, no profile

- GIVEN no session
- WHEN a request is made to `GET /apps/thematiq/api/document-style`
- THEN the response MUST NOT contain the profile

### Requirement: An administrator sets document assets

The Documents block on Settings > Administration > Theming MUST let an administrator upload a document logo and a cover image and add one footer line. Uploads MUST be checked for type and size, and an SVG containing script MUST be refused. Without uploads the profile MUST fall back to the active set's logo and the email footer settings. The endpoints MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]`.

#### Scenario: An administrator sets a print logo for documents

- GIVEN an administrator on Settings > Administration > Theming
- WHEN they upload a PNG print logo in the Documents block
- THEN the profile MUST name that logo
- AND the header logo on web pages MUST be unchanged

#### Scenario: No document logo falls back to the house style logo

- GIVEN no document logo uploaded and an active set with a logo
- WHEN a signed-in user requests the profile
- THEN the profile logo MUST be the active set's logo
