---
kind: code
depends_on:
  - nc-variable-inventory
  - theme-vocabulary-complete
  - internal-variable-tokens
---

## Why

The token editor shows 179 tokens in four tabs, and its docs still say 53. After changes 2 and 3 the registry holds about 790: 101 theme tokens, 123 component tokens, about 513 Nextcloud internals and 54 Conduction ones. Four flat tabs at that size are a scroll, not an editor. An admin looking for the date picker's hover colour has no way to find it.

Three more things become necessary once every variable is editable. An admin needs to see what Nextcloud would show without them, so they know what they are replacing. They need a warning before changing the header height. And the 45 newly settable theme tokens, most of which Nextcloud calculates per theme, need a way to take a separate dark value.

## What Changes

- **Search across every token.** One field filters by label, CSS name and component, across all groups.
- **Groups per component.** Theme tokens keep the four functional tabs. Component and internal tokens are grouped by the component that owns them, under translated headings, collapsed until opened.
- **Advanced, collapsed, with a warning.** The 13 structural variables sit in an Advanced group. Opening it shows a warning that these values change Nextcloud's layout and can break it.
- **Stock value and note beside each field.** Each row shows Nextcloud's own value for the current theme and, where one exists, the note recorded in the inventory.
- **A dark value per row.** Rows for the 45 settable theme tokens, and for internal colour tokens, can take a separate dark value. Brand rows keep the #705 behaviour: the writer derives their dark value.
- **The count is the registry's.** The editor and the docs show the number the registry reports, so they cannot go stale again.
- **Import and export cover everything.** The overrides file round-trips every registry token, including dark values.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `token-editor-ui`: "Functional Tab Groups" changes from exactly four tabs to four theme tabs plus component groups and an Advanced group; search, stock values, notes and dark values are added.
- `token-import-export`: export and import cover every registry token and its dark value.

## Impact

- Changed: `js/admin.js` (editor rendering, search, groups, dark values), `css/admin.css`, `templates/settings/admin.php`, the overrides writer and reader, `docs/features/token-editor.md`, `docs/features/import-export.md`.
- New strings: group headings, search label, the Advanced warning, the dark-value control. All through `t('thematiq', ...)` with Dutch translations.
- Performance: groups render their rows when opened, so the settings page does not build 780 rows on load.
