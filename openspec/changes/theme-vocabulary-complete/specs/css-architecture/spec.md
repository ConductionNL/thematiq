## ADDED Requirements

### Requirement: The theme scopes layer sits between the design system and the component scopes
The app MUST inject `css/theme-scopes.css` after every stylesheet of the active design system and before `css/component-scopes.css`, in every themed render context.

@e2e exclude Stylesheet order is a server-side injection fact; the browser-visible consequence is tested in `nextcloud-variable-mapping` ("A set value reaches the page").

#### Scenario: Order on a workspace page
- GIVEN the nldesign design system is active
- WHEN a workspace page is rendered
- THEN the injected stylesheets MUST list `systems/nldesign/element-overrides` before `theme-scopes`
- AND they MUST list `theme-scopes` before `component-scopes`

#### Scenario: Order on the login page
- GIVEN the lasuite design system is active
- WHEN the login page is rendered
- THEN `theme-scopes` MUST follow the last lasuite stylesheet
- AND it MUST precede `component-scopes`

#### Scenario: A component scope falls back to a theme-scoped value
- GIVEN a set declares `--nldesign-nc-color-warning-hover` and no component token for the warning button
- WHEN a page with a warning button renders
- THEN the button's hover colour MUST be the set's value

### Requirement: Dark-mode compatibility variables follow Nextcloud in dark unless given a dark value
REQ-CSS-007 MUST keep holding for every layer: `--color-main-background`, `--color-main-background-rgb`, `--color-main-background-translucent`, `--color-background-plain`, `--background-invert-if-dark` and `--background-invert-if-bright` MUST resolve to Nextcloud's own value in every dark theme unless a set's dark variant file or a dark admin value provides one.

@e2e exclude The browser check is "Unset under the dark theme" in `nextcloud-variable-mapping`, run once per variable in this list.

#### Scenario: A light-only admin value leaves dark mode alone
- GIVEN the admin saves `--color-main-background: #fdfcf8` with no dark value
- AND the user has chosen Nextcloud's dark theme
- WHEN a page renders
- THEN the computed `--color-main-background` MUST equal Nextcloud's dark value

#### Scenario: The rule for every other admin override is unchanged
- GIVEN the admin saves `--color-primary: #24578f`
- AND the user has chosen Nextcloud's dark theme
- WHEN a page renders
- THEN the computed `--color-primary` MUST be the dark value the overrides writer derives from `#24578f`, as for every brand override
