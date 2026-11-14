# The plugin's set file keeps its dark block

## Why

Two specs disagreed. `openproject-set-file` said an uploaded set file's `dark` block MUST be left out and named. `dark-colours-from-a-file` said the same block MUST be read and kept as the set's dark values. The code keeps it: `lib/Service/OpenProjectSetFile.php` reads the dark block into the uploaded set's dark values, and `CustomTokenSetUploadWritesTest::testDarkValuesFromAFileAreKept` proves it. Ruben decided on 12 Nov 2026 (decision 392) to keep the dark block, so `openproject-set-file` follows `dark-colours-from-a-file`.

## What changes

- "The plugin's set file becomes a custom token set" no longer leaves the dark block out. The dark block is read as `dark-colours-from-a-file` says, and only unknown roles or keys and invalid values are left out and named.

## Matrix rows (`openspec/parity/capabilities.json`)

| row | requirement |
|---|---|
| `int-openproject-set-file` | The plugin's set file becomes a custom token set (unchanged heading) |
