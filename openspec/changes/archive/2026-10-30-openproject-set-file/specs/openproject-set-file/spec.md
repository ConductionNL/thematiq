# Spec delta: openproject set file (openproject-set-file)

## ADDED Requirements

### Requirement: Any token set can be downloaded as the plugin's set file

The set file download of a token set MUST give the OpenProject plugin's set file:
`id`, `name`, the eighteen roles under `tokens`, a `logo` block, and the optional `vocabulary` and
`dark` blocks of the set's shell catalogue entry, with no other key, as `thematiq-{id}.json`. An
uploaded set MUST name no logo file.

#### Scenario: A shipped set as a set file
@e2e exclude the file's contents are what matters and a browser only adds a save dialog; proven by tests/Unit/Controller/OpenProjectSetFileControllerTest.php (testTheSetFileHoldsTheRolesAndTheLogo) and tests/Unit/Service/OpenProjectSetFileTest.php (testASetFileComesBackWithTheSameRoles)
- GIVEN the shipped set `cunningham`
- WHEN its set file is downloaded
- THEN the file MUST hold `tokens.action` `#1f883d`
- AND uploading the file again MUST give every role the same value

### Requirement: The plugin's set file becomes a custom token set

An upload that has an `id`, a `tokens` object and no key outside the set file's keys MUST be read as
a set file. Each role MUST be stored under the name thematiq's role mapper reads it from, and each
vocabulary key with `--nldesign-` in front. An unknown role or key, a value that is not text or
holds a brace, and the dark block MUST be left out and named. Every value MUST still pass the token
set validator.

#### Scenario: A file the plugin downloads
@e2e exclude a comparison of parsed files with no page; proven by tests/Unit/Service/OpenProjectSetFileTest.php (testAPluginSetFileKeepsEveryRole, testWhatIsLeftOutIsNamed)
- GIVEN the set files the plugin writes for `cunningham` and `zuiddrecht`
- WHEN each is uploaded
- THEN each role MUST have the plugin's value
- AND the dark block MUST be named as left out
