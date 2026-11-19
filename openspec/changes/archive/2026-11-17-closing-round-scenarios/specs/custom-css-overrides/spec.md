# custom-css-overrides Specification (delta)

## MODIFIED Requirements

### Requirement: Custom Overrides File
The system MUST maintain a `custom-overrides.css` file in the nldesign app's CSS directory. This file MUST be written exclusively by the theme editor backend — no other write path exists.

#### Scenario: File does not exist on fresh install
@e2e exclude a browser cannot delete a file from the app directory, and every themed render recreates it; PHPUnit tests/Unit/Service/CustomOverridesServicePerSetTest.php::testNoSetNamedMeansTheActiveSet asserts ensureExists() creates the missing file as an empty block, and tests/Unit/Service/CssInjectionServiceTest.php::testCustomOverridesAlwaysLoadedLast asserts it runs before the link is emitted
- GIVEN the nldesign app is freshly installed
- WHEN Nextcloud loads the theming CSS
- THEN `custom-overrides.css` MUST NOT be required to exist
- AND the CSS stack MUST function correctly with the file absent

#### Scenario: File exists with custom tokens
- GIVEN `custom-overrides.css` contains one or more `--color-*` overrides
- WHEN the browser loads the CSS stack
- THEN the overrides MUST apply on top of all other CSS layers
- AND they MUST take effect without a page reload (only on next load)

#### Scenario: An administrator adapts a shipped set
- GIVEN `utrecht` is the active token set and no token is overridden
- WHEN the administrator sets "Primary color" to `#a00078` in the token editor and saves
- THEN every page MUST use `#a00078` as the primary colour
- AND every token the administrator did not change MUST keep the value `utrecht` gives it
- AND the shipped `utrecht` set MUST stay unchanged
