# nldesign-solid-app-shell Specification

## Purpose
The nldesign theme points `--color-main-background-blur` at `--color-main-background` outside the login page, the way the high-contrast system already does. Created by change `nldesign-solid-app-shell` (archived 2026-10-30 under `openspec/changes/archive/`). Requirements: The app shell is solid under nldesign.

## Requirements

### Requirement: The app shell is solid under nldesign

The nldesign theme SHALL set `--color-main-background-blur` to `--color-main-background` on every page except the
login page.

#### Scenario: the AI chat button sits on a white shell

- **GIVEN** a nextcloud-vue app under an nldesign set in the light theme
- **THEN** `#content-vue` has the background `rgb(255, 255, 255)`, not `rgba(255, 255, 255, 0.8)`

#### Scenario: the login card stays frosted

- **GIVEN** the login page under an nldesign set
- **THEN** `--color-main-background-blur` keeps Nextcloud's value
