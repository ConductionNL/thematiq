# Spec delta: Custom Token Sets (apply-without-reload)

Upload and delete keep every guarantee they have. What changes is that their result is visible in
the dropdown the moment the request returns, instead of after a reload the toast used to ask for.

## ADDED Requirements

### Requirement: An Uploaded Set Is Selectable Without A Reload
After a successful upload the admin panel MUST bring the token-set dropdown and its in-memory
catalogue in line with the server's selectable list, so the new set can be chosen at once.

#### Scenario: Option appears after upload
- GIVEN the admin uploads a theme named "Midden-Delfland"
- WHEN the upload response is `200`
- THEN the client MUST re-read `GET /settings/tokensets`
- AND the dropdown MUST gain `<option value="custom-midden-delfland">Midden-Delfland</option>` in
  alphabetical position, carrying `data-design-system`
- AND `tokenSetsData['custom-midden-delfland']` MUST hold the catalogue entry (name, theming,
  warnings), so the apply and theming-sync dialogs can read it
- AND the page MUST NOT reload

#### Scenario: Upload does not select or apply
- GIVEN the option was added
- WHEN the upload flow completes
- THEN the dropdown's selection MUST be unchanged and no apply dialog MUST open
- AND the notification MUST tell the admin the set is in the dropdown and to select it to apply it
- AND the dropdown MUST receive focus

#### Scenario: Catalogue refresh fails
- GIVEN `GET /settings/tokensets` fails after a successful upload
- WHEN the client handles it
- THEN the notification MUST fall back to asking for a reload, and the upload MUST still count as
  stored

### Requirement: A Deleted Set Leaves The Dropdown Without A Reload
After a successful delete the admin panel MUST remove the set's option and catalogue entry, and MUST
mirror the server's reset to `nextcloud` on the page when the deleted set was the one on it.

#### Scenario: Deleting the active set
- GIVEN `custom-openwoo` is the active set and is on the page
- WHEN the admin confirms its deletion and the server answers `{status: "ok"}`
- THEN its `<option>` and `tokenSetsData` entry MUST be removed
- AND the page's stylesheet run MUST be swapped to `nextcloud`'s (empty)
- AND the dropdown MUST show `nextcloud`
- AND the page MUST NOT reload

#### Scenario: Deleting an inactive set
- GIVEN `custom-x` exists but is neither selected nor on the page
- WHEN it is deleted
- THEN only its option and entry MUST be removed; the page's stylesheets MUST be untouched
