# Spec: Configuration portability

## ADDED Requirements

### Requirement: The Layout Options Travel In The Bundle
The bundle MUST carry the six layout choices under `config.layoutOptions` (`workplaceLayout`,
`brandStripe`, `navigationWidth`, `navigationActiveStyle`, `brandStripePlacement`,
`loginWatermark`), each as the string the administrator stored, the empty "follow the theme"
included, and this change MUST bump `bundleVersion` to 4. Import MUST validate each against what
its option accepts in phase 1 and write nothing when one fails, naming the key. A bundle without
the section, or without one of its keys, MUST import that option as "follow the theme".

#### Scenario: The layout choices survive a round trip
@e2e exclude JSON bundle round trip, not a page; proven by tests/Unit/Service/ConfigBundleServiceTest.php
- GIVEN an instance with the light layout, the stripe off, a 264px navigation, the soft entry, the `login` placement and the watermark left to the theme
- WHEN the bundle is exported, the instance reset and the bundle imported
- THEN each choice MUST be stored as it was, the empty watermark choice included

#### Scenario: A bundle from before the options travelled imports as follow
@e2e exclude JSON bundle round trip, not a page; proven by tests/Unit/Service/ConfigBundleServiceTest.php
- GIVEN a version 3 bundle without `config.layoutOptions` and an instance that stored the light layout
- WHEN it is imported
- THEN the layout MUST follow the theme again

#### Scenario: A value an option refuses blocks the import
@e2e exclude JSON bundle round trip, not a page; proven by tests/Unit/Service/ConfigBundleServiceTest.php
- GIVEN a bundle with `config.layoutOptions.navigationWidth = "9000"`
- WHEN it is imported
- THEN the import MUST be invalid, the error MUST name `config.layoutOptions.navigationWidth` and nothing MUST be written
