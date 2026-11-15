## ADDED Requirements

### Requirement: Action menu link items read as menu entries

An NcActionLink item (`a.action-link`) in an action menu SHALL be drawn in the
main text colour without an underline under every nldesign set, like the
button items of the same menu. A link in prose SHALL keep the link colour and
the set's link decoration.

#### Scenario: the case page Acties menu
- GIVEN dossiq's case page "Acties" menu with button items and the link items "Een bug melden" and "Documentatie"
- WHEN it opens under the Zuiddrecht set
- THEN every item, button or link, reads in the main text colour
- AND no item is underlined
