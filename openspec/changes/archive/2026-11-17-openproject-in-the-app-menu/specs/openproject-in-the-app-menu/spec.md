# Spec delta: OpenProject in the apps menu (openproject-in-the-app-menu)

## ADDED Requirements

### Requirement: The apps menu lists OpenProject's modules

With an http or https address in `openproject_url`, the workplace apps menu MUST list OpenProject's
modules in a section of their own, after Nextcloud's apps, each a link to the module's global page
on that address. The modules MUST be those in `openproject_modules` that are known, in their order,
at most nine, and the default list when none is known. An address that is not http or https MUST be
refused and MUST NOT be linked. Only an administrator MUST be able to set it.

#### Scenario: An administrator gives OpenProject's address
@e2e exclude the section needs the workplace header and a configured address; proven by tests/vitest/appMenu.spec.js (adds OpenProject's modules as a section of links) and tests/Unit/Service/AppMenuServiceTest.php (testOpenProjectsModulesJoinTheMenu, testTheAddressAndTheModulesAreChecked), and looked at live on Nextcloud 35.0.1
- GIVEN `openproject_url` is `https://op.example.org`
- WHEN the apps menu opens
- THEN it MUST show a section "OpenProject" with links to `https://op.example.org/work_packages`, `/gantt`, `/boards` and `/meetings`

#### Scenario: An address that is not one
@e2e exclude a status code; proven by tests/Unit/Controller/OpenProjectLinkControllerTest.php (testTheAddressIsSavedReadAndCleared)
- WHEN an administrator saves `javascript:alert(1)`
- THEN the answer MUST be 400 and nothing MUST be stored
