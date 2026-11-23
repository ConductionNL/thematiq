---
kind: code
---

# Four theme sheet fixes from the pixel pass

## Why

The pixel and film passes on the demo stack (23 November 2026) found four faults in the theme's own
sheets, fixed in #1791, #1796, #1797 and #1805 without a spec. Each is a rule other work can break
again, so each gets a requirement.

## What changes

- **Static sheets follow the theming cachebuster** (#1805). The layer sheets thematiq links
  (`header-workplace.css`, `workplace-apps/*`, the brand stripe, the navigation width and the rest)
  carry `?v=<installed_version>-<theming cachebuster>`, the pair the scripts carry, so a deploy that
  raises only the cachebuster never pairs a new script with a cached sheet.
- **The word separator dot draws in Fira Sans** (#1797). Fira lacks U+2E31, which Deck writes between
  "Modified" and "Created". A small face with only Fira's middle dot under that code point sits beside
  every Fira face.
- **App tiles keep their own colours** (#1791). A launchpad app tile is a link drawn as a button; the
  link rules no longer paint or underline it.
- **Only a data table greys its rows on hover** (#1796). A layout grid drawn as a table, such as
  Calendar's week, no longer greys the whole page under the pointer.

## Not in this change

- New behaviour. Every task below is built and cites its pull request.

## Impact

- `lib/Service/CssInjectionService.php`
- `scripts/build-fonts.js`, `css/fonts.css`, `css/systems/nldesign/fonts.css`, `fira-sans-separator.otf`
- `css/systems/nldesign/theme.css`, `element-overrides.css`, `css/systems/high-contrast/theme.css`
