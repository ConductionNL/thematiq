# custom-fonts Specification (delta)

## MODIFIED Requirements

### Requirement: Font Management Lifecycle

The admin panel MUST list stored fonts (name, role, size) and provide delete. Deleting a
font MUST remove the appdata file and its manifest entry, bump the font revision (cache
bust), and cause the generated stylesheet to drop its rules so rendering falls back to the
shipped chain. List and delete endpoints MUST be admin-only and CSRF-protected.

#### Scenario: Admin deletes a font
@e2e exclude covered by FontServiceTest::testDeleteRemovesFileManifestAndBumpsRev and
FontControllerTest delete cases (PHPUnit); live rendering-fallback check deferred to
tasks.md#task-6.4

- GIVEN a stored font `custom-rijks-sans` currently referenced by the generated stylesheet
- WHEN the admin deletes it and reloads
- THEN the appdata file and the manifest entry MUST be gone
- AND `/apps/nldesign/fonts/css` MUST no longer reference the family (new ETag)
- AND body text MUST render with the shipped Fira Sans chain

#### Scenario: Admin sees the stored fonts listed
- GIVEN two stored fonts, one for the body role and one for the heading role
- WHEN the admin opens Settings > Administration > Theming
- THEN the Custom fonts list MUST show both fonts with their name, role and size
- AND each font in the list MUST have a delete action
- AND with no stored fonts the list MUST say that no fonts are uploaded
