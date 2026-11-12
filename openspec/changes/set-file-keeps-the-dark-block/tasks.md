# Tasks: set-file-keeps-the-dark-block

## 1. Spec

- [x] 1.1 Change "The plugin's set file becomes a custom token set" so the dark block is kept, as `dark-colours-from-a-file` says. Verify: `openspec validate set-file-keeps-the-dark-block --strict`.

## 2. Code check

- [ ] 2.1 Confirm `tests/Unit/Service/OpenProjectSetFileTest.php` (`testWhatIsLeftOutIsNamed`) no longer expects the dark block among what is left out, and that `lib/Service/OpenProjectSetFile.php` names only unknown roles and keys and invalid values.

## 3. Close out

- [ ] 3.1 Archive this change so the main spec follows.
