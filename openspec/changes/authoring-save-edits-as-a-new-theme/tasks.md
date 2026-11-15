## 1. Build

- [x] 1.1 The save dialog on the token editor: shown when saving edits while no token set is active, with a name field, "Bewaren als nieuw thema", "Het Nextcloud-thema aanpassen" and "Annuleren".
- [x] 1.2 Save as new theme: store the edited tokens as a custom token set under the given name and list it in the active token set dropdown without a reload.
- [x] 1.3 Keep values that fit no token set as overrides, and list them to the admin after saving.
- [x] 1.4 The "Niet vragen voor het opslaan" preference.
- [x] 1.5 Unit tests for the new set's content, the leftover overrides and a duplicate name.

## 2. Hand-over

- [x] 2.1 `@spec` tags and an e2e test or a reason-bearing `@e2e exclude` per scenario.

Notes (build): the dialog, the new set in the dropdown, the name check and the "do not ask" switch
already shipped with `76efc80f0` (save the running theme as a theme). This build adds what the spec
asks beyond it: the values no token set can hold are saved as overrides of the new theme and listed
(1.3), and the Dutch labels the board draws (1.1, 1.4).
