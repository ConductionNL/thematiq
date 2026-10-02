# Spec delta: per-app theming (per-app-theming)

Next to excluding an app, an administrator can give an app its own brand.

## ADDED Requirements

### Requirement: An administrator gives an app its own brand

Settings > Administration > Theming MUST offer a Brand per app block in which an administrator maps an installed app to a token set and, optionally, a large and a small logo. The app MUST NOT be excluded from theming and MUST NOT be one of the protected ids (`thematiq`, `settings`, `theming`). The block MUST state that the app's name stays Nextcloud's. The endpoints MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]`, and logo uploads MUST be checked for type and size.

#### Scenario: An administrator gives the knowledge base its own brand

- GIVEN an administrator on Settings > Administration > Theming with the Collectives app installed
- WHEN they map Collectives to set `kennisbank` with a large and a small logo and save
- THEN a user who opens Collectives MUST see the `kennisbank` colours and the large logo in the header
- AND a user who opens Files MUST see the house style as before

#### Scenario: A protected app cannot be branded

- GIVEN an administrator in the Brand per app block
- WHEN they try to map the `settings` app
- THEN the save MUST fail with a message that the settings pages always follow the house style

### Requirement: The small logo is used on narrow screens

For a branded app with a small logo, the header MUST show the small logo below Nextcloud's narrow-screen breakpoint and the large logo above it. Without a small logo the large logo MUST be used at every width.

#### Scenario: A phone shows the small logo

- GIVEN Collectives branded with a large and a small logo
- WHEN a user opens Collectives on a 360 px wide screen
- THEN the header MUST show the small logo
