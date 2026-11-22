# openproject-in-the-app-menu

## REMOVED Requirements

### Requirement: The apps menu lists OpenProject's modules

**Reason**: Generalised into external-apps-in-the-app-menu, which lists OpenProject beside
Open-Xchange, Element, XWiki, Forgejo and BigBlueButton under one heading "External apps".
**Migration**: Nothing to do. OpenProject keeps the app config keys `openproject_url` and
`openproject_modules`, its modules and its admin-only `/settings/openproject` endpoint.
