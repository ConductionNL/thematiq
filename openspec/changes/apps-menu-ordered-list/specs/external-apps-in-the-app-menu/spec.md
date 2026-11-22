# external-apps-in-the-app-menu

## ADDED Requirements

### Requirement: The apps menu lists the external apps that have an address
An administrator MUST be able to give an address per external app on the thematiq admin page:
OpenProject (`openproject_url`, with its modules from `openproject_modules`), and Open-Xchange
(`mail`), Element (`chat`), XWiki (`wiki`), Forgejo (`code`) and BigBlueButton (`video`) in the app
config key `external_apps`. Only an http or https address MUST be accepted; one address that is not
MUST refuse the whole save with 400, and nothing MUST be stored. Only an administrator MUST be able
to read or save the addresses. In the workplace apps menu, every app with an address MUST be listed
under the heading "External apps", after the Nextcloud apps: OpenProject as its modules (at most
nine, the default `work_packages, gantt, boards, meetings`), each other app as one entry with its
name and a second line. An app without an address MUST be left out, and without any address the
heading MUST NOT be drawn. Each link MUST open in a new tab with `rel="noopener noreferrer"` and
MUST say so to assistive technology.

#### Scenario: An administrator gives two addresses
- GIVEN an administrator saves `https://op.example.org` for OpenProject and `https://code.example.org` for Forgejo
- WHEN the apps menu opens with "All apps" unfolded
- THEN "External apps" MUST list OpenProject's four default modules and "Forgejo" with the line "Code"
- AND "Forgejo" MUST link to `https://code.example.org` in a new tab
- AND Open-Xchange, Element, XWiki and BigBlueButton MUST NOT be listed

#### Scenario: An address that is not one
- WHEN an administrator saves `javascript:alert(1)` for Element
- THEN the answer MUST be 400 and no address MUST be stored

#### Scenario: Only an administrator sets the addresses
@e2e exclude Nextcloud's AuthorizedAdminSetting middleware answers; proven by tests/Unit/Controller/ExternalAppsControllerTest.php (testEveryMethodIsAdminOnly)
- GIVEN a signed-in person who is not an administrator
- WHEN they save an address
- THEN the answer MUST be 403

#### Scenario: OpenProject keeps its address
@e2e exclude Pure service logic: PHPUnit tests/Unit/Service/ExternalAppsTest.php (testOpenProjectKeepsItsKeys)
- GIVEN `openproject_url` was set before this change
- WHEN the apps menu opens
- THEN OpenProject's modules MUST be listed under "External apps"
