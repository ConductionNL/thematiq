# Save my edits as a new theme

## Why

Ruben decided on 13 Nov (decision 406) that the drawn board is canon. TqNieuwThema draws a dialog
"Deze wijzigingen bewaren als nieuw thema?" that opens when an admin saves edits made on
Nextcloud's own theme. The admin names a new theme and saves the edits as an own token set, or
writes them over the Nextcloud theme. Today Save always stores the edits as overrides on top of
the active base (`POST /settings/overrides`). No spec and no code turn edits into a new set.

### Matrix rows (thematiq `openspec/parity/capabilities.json`)

| row | what changes |
|---|---|
| `aut-save-as-new-theme` (new) | an admin saves the edits as a new, named theme and picks it as the active set afterwards |

## What changes

- Saving edits made on Nextcloud's own theme asks first: save as a new theme, or change the
  Nextcloud theme.
- A new theme is a custom token set under the name the admin gives. It appears in the active
  token set list.
- Values that do not fit in a token set stay saved as overrides, and the admin sees which.
- The admin can switch the question off ("Niet vragen voor het opslaan").

## Impact

- The token editor's save flow (`js/`), the custom token set store
  (`lib/Service/CustomTokenSetService.php`). Not built yet: the new row is
  `specified`.
