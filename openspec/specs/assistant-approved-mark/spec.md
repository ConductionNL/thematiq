# assistant-approved-mark Specification

## Purpose
An organisation marks the fleet's AI assistant as the one it sanctioned: thematiq holds the statement, the assistant panel in nextcloud-vue draws it. Created by archiving change surfaces-assistant-approved-mark.

## Requirements
### Requirement: An administrator turns the approved mark on

Settings > Administration > Theming MUST offer an AI assistant block with a toggle for the approved mark, an organisation name that defaults to the email footer organisation name, and a logo that defaults to the active house style logo. The mark MUST be off by default. The app MUST refuse to turn it on while the organisation name is empty.

#### Scenario: An administrator turns on the approved mark

@e2e exclude owed with the browser; proven by tests/vitest/admin-assistant-mark.spec.js and tests/Unit/Service/AssistantMarkServiceTest.php::testOnWithDefaults

- GIVEN an administrator on Settings > Administration > Theming with the email footer organisation "Gemeente Voorbeeld"
- WHEN they turn on the approved mark and save
- THEN the block MUST preview "Approved by Gemeente Voorbeeld" with the house style logo

#### Scenario: An empty name keeps the mark off

@e2e exclude needs an empty email footer on the shared CI instance; proven by tests/Unit/Service/AssistantMarkServiceTest.php::testEmptyNameKeepsTheMarkOff and tests/vitest/admin-assistant-mark.spec.js

- GIVEN no organisation name in the email footer and none entered in the block
- WHEN an administrator tries to turn on the approved mark
- THEN the save MUST fail with a message asking for the organisation name
- AND the mark MUST stay off

### Requirement: Signed-in users read the mark

`GET /apps/thematiq/api/assistant-mark` MUST answer signed-in users with whether the mark is on and, when it is, the label "Approved by <organisation>" translated into the user's language, the organisation name, and the logo URL with alternative text. When the mark is off, the response MUST say only that it is off. The endpoint MUST NOT answer without a session.

#### Scenario: A Dutch user gets a Dutch label

@e2e exclude API, not a page; proven by tests/Unit/Service/AssistantMarkServiceTest.php::testControllerAnswers

- GIVEN the mark is on for "Gemeente Voorbeeld"
- WHEN a signed-in user whose language is Dutch requests `GET /apps/thematiq/api/assistant-mark`
- THEN the label MUST be the Dutch translation of "Approved by Gemeente Voorbeeld"

#### Scenario: The mark is off

@e2e exclude API, not a page; proven by tests/Unit/Service/AssistantMarkServiceTest.php::testOffByDefault

- GIVEN the mark is off
- WHEN a signed-in user requests the endpoint
- THEN the response MUST state `enabled: false` and carry no label or logo

### Requirement: The assistant panel shows the mark when it is on

When thematiq is installed and the mark is on, the fleet's AI assistant panel MUST show the logo and the label in its footer; when thematiq is not installed or the mark is off, the panel MUST show nothing in that place. This requirement is met by the nextcloud-vue half of this change.

#### Scenario: A user recognises the sanctioned assistant

@e2e exclude the panel is the nextcloud-vue half, tracked in ConductionNL/nextcloud-vue#1298; the endpoint it reads is proven by tests/Unit/Service/AssistantMarkServiceTest.php::testOnWithOwnNameAndLogo

- GIVEN the mark is on for "Gemeente Voorbeeld" and a user opens a Conduction app
- WHEN they open the AI assistant panel
- THEN the panel footer MUST show the organisation logo and "Approved by Gemeente Voorbeeld"
