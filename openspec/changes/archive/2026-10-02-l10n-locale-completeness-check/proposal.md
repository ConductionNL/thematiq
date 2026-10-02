---
kind: code
---

## Why

`l10n/nl.json` is missing 3 of the 94 translation keys that exist in `l10n/en.json` (the i18n
source of truth, per the fleet rule "i18n keys = ENGLISH source"):

- `{themed} of {total} apps themed` — used at `js/admin.js:1097` to label the per-app theming
  dropdown trigger button (e.g. "3 of 12 apps themed").
- `Search apps` — used at `js/admin.js:1087` as the `aria-label` on the dropdown's search input.
- `Search apps…` — used at `js/admin.js:1086` as the search input's placeholder text.

Verified directly: `python3 -c "import json; en=json.load(open('l10n/en.json'))['translations']; nl=json.load(open('l10n/nl.json'))['translations']; print([k for k in en if k not in nl])"` →
`['{themed} of {total} apps themed', 'Search apps', 'Search apps…']`. All three strings ship in
the app-theming dropdown added by the per-app-theming-toggle change (archived
`openspec/changes/archive/2026-06-14-per-app-theming-toggle`), which post-dates the last full
`l10n/nl.json` sync. Dutch admins using the per-app theming panel — the primary persona this app
exists for — silently see three English strings on an otherwise fully Dutch-localized screen.

This is a tooling gap, not just a data gap: `tests/l10n/check-l10n.js` (invoked via
`npm run test:l10n`, `L10N_SRC_DIR=js`) only checks that every key **used** in `js/**` exists in
`l10n/en.json` — it does not check that every key **in** `en.json` also exists (non-empty) in the
other 36 shipped locale files. Running it against current HEAD confirms this:
`L10N_SRC_DIR=js node tests/l10n/check-l10n.js` prints `l10n-check: OK — every used translation
key is present in l10n/en.json` (exit 0) despite the 3 missing `nl.json` keys — the exact defect
this change is about slips past the app's own l10n gate. Any future key added to `js/admin.js`
without a matching `nl.json` (or other-locale) entry will pass CI silently, reproducing this bug.

## What Changes

- Add the 3 missing keys (and their Dutch translations) to `l10n/nl.json`, keeping the file
  sorted/structured consistently with its existing entries.
- Extend `tests/l10n/check-l10n.js` with a second check mode: for every locale file under `l10n/`
  other than `en.json`, verify every key present in `en.json` also exists as a non-empty string in
  that locale file. Report missing/empty keys per locale (mirroring the existing missing-key
  report format) and exit non-zero on any gap.
- Wire the new check into `npm run test:l10n` (and `test:l10n:write` where a sensible
  autofill/stub behavior exists — e.g. copying the English source string as a marked-untranslated
  placeholder) so CI catches this class of regression going forward.
- Not BREAKING: purely additive translations plus a stricter (previously absent) validation step.

## Impact

- `l10n/nl.json` — add 3 keys.
- `tests/l10n/check-l10n.js` — add cross-locale completeness check.
- `package.json` — no script rename needed; `test:l10n` behavior gains the new check.
