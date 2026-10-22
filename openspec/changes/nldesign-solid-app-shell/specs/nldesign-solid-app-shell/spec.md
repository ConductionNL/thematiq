# nldesign-solid-app-shell Delta

## ADDED Requirements

### Requirement: The app shell is solid under nldesign

The nldesign theme SHALL set `--color-main-background-blur` to `--color-main-background` on every page except the
login page.

#### Scenario: the AI chat button sits on a white shell

- **GIVEN** a nextcloud-vue app under an nldesign set in the light theme
- **THEN** `#content-vue` has the background `rgb(255, 255, 255)`, not `rgba(255, 255, 255, 0.8)`

#### Scenario: the login card stays frosted

- **GIVEN** the login page under an nldesign set
- **THEN** `--color-main-background-blur` keeps Nextcloud's value
