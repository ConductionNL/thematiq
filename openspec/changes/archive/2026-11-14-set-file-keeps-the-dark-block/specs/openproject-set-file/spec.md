## MODIFIED Requirements

### Requirement: The plugin's set file becomes a custom token set

An upload that has an `id`, a `tokens` object and no key outside the set file's keys MUST be read as
a set file. Each role MUST be stored under the name thematiq's role mapper reads it from, and each
vocabulary key with `--nldesign-` in front. The `dark` block MUST be read as the set's dark values, as
`dark-colours-from-a-file` says. An unknown role or key, and a value that is not text or holds a brace,
MUST be left out and named. Every value MUST still pass the token set validator.

#### Scenario: A file the plugin downloads
@e2e exclude a comparison of parsed files with no page; proven by tests/Unit/Service/OpenProjectSetFileTest.php (testAPluginSetFileKeepsEveryRole, testWhatIsLeftOutIsNamed)
- GIVEN the set files the plugin writes for `cunningham` and `zuiddrecht`
- WHEN each is uploaded
- THEN each role MUST have the plugin's value
- AND the dark block MUST be kept as the set's dark values, not named as left out
