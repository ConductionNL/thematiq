# Spec delta: assistant approved mark (assistant-approved-mark)

A new capability. An organisation marks the fleet's AI assistant as the one it sanctioned.

## ADDED Requirements

### Requirement: An administrator turns the approved mark on

Settings > Administration > Theming MUST offer an AI assistant block with a toggle for the approved mark, an organisation name that defaults to the email footer organisation name, and a logo that defaults to the active house style logo. The mark MUST be off by default. The app MUST refuse to turn it on while the organisation name is empty.

#### Scenario: An administrator turns on the approved mark

- GIVEN an administrator on Settings > Administration > Theming with the email footer organisation "Gemeente Voorbeeld"
- WHEN they turn on the approved mark and save
- THEN the block MUST preview "Approved by Gemeente Voorbeeld" with the house style logo

#### Scenario: An empty name keeps the mark off

- GIVEN no organisation name in the email footer and none entered in the block
- WHEN an administrator tries to turn on the approved mark
- THEN the save MUST fail with a message asking for the organisation name
- AND the mark MUST stay off

### Requirement: Signed-in users read the mark

`GET /apps/thematiq/api/assistant-mark` MUST answer signed-in users with whether the mark is on and, when it is, the label "Approved by <organisation>" translated into the user's language, the organisation name, and the logo URL with alternative text. When the mark is off, the response MUST say only that it is off. The endpoint MUST NOT answer without a session.

#### Scenario: A Dutch user gets a Dutch label

- GIVEN the mark is on for "Gemeente Voorbeeld"
- WHEN a signed-in user whose language is Dutch requests `GET /apps/thematiq/api/assistant-mark`
- THEN the label MUST be the Dutch translation of "Approved by Gemeente Voorbeeld"

#### Scenario: The mark is off

- GIVEN the mark is off
- WHEN a signed-in user requests the endpoint
- THEN the response MUST state `enabled: false` and carry no label or logo

### Requirement: The assistant panel shows the mark when it is on

When thematiq is installed and the mark is on, the fleet's AI assistant panel MUST show the logo and the label in its footer; when thematiq is not installed or the mark is off, the panel MUST show nothing in that place. This requirement is met by the nextcloud-vue half of this change.

#### Scenario: A user recognises the sanctioned assistant

- GIVEN the mark is on for "Gemeente Voorbeeld" and a user opens a Conduction app
- WHEN they open the AI assistant panel
- THEN the panel footer MUST show the organisation logo and "Approved by Gemeente Voorbeeld"
