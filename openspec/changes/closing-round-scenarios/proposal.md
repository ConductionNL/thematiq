---
kind: spec
---

# Proposal: closing-round-scenarios

## Summary

The coverage audit of 12 November 2026 found two built rows whose requirement says only part of what the row claims. The code does all of it; the spec does not say it yet.

- `app-live-preview-shell` names the header, the navigation and the buttons. `Live Preview Box` has THENs for the header bar and two buttons, not for the navigation, while `templates/settings/admin.php` draws a navigation list with captions and a filled selected entry.
- `typ-font-list-delete` names listing and deleting uploaded fonts. `Font Management Lifecycle` has a delete scenario but no scenario for the list, while `#nldesign-font-list` in `templates/settings/admin.php` and `js/admin.js` render the stored fonts with a delete action.

## What changes

- MODIFIED `admin-settings` `Live Preview Box`: a scenario for the app shell navigation.
- MODIFIED `custom-fonts` `Font Management Lifecycle`: a scenario for the list of stored fonts.

## Screens

TqTheming (the preview) and TqLettertypen (the font list). See `screens.md`.
