## 1. Fix the missing Dutch translations

- [x] 1.1 Add `{themed} of {total} apps themed` to `l10n/nl.json` with a Dutch translation
      (e.g. `"{themed} van {total} apps met huisstijl"`), preserving the `{themed}`/`{total}`
      placeholders.
- [x] 1.2 Add `Search apps` to `l10n/nl.json` (e.g. `"Zoek apps"`).
- [x] 1.3 Add `Search apps…` to `l10n/nl.json` (e.g. `"Zoek apps…"`).
- [x] 1.4 Confirm `l10n/nl.json` remains valid JSON and its `translations` object key count now
      matches `l10n/en.json` (94).

## 2. Extend the l10n check tool with cross-locale completeness

- [x] 2.1 In `tests/l10n/check-l10n.js`, after the existing "keys used in js/** exist in en.json"
      check, add a new pass that reads every `l10n/*.json` file except `en.json`.
- [x] 2.2 For each such locale file, compute the set of keys present in `en.json`'s
      `translations` but absent, or present with an empty string, in the locale file.
- [x] 2.3 Print a per-locale report of missing/empty keys (reuse the existing report formatting
      style in the file) and set the process exit code non-zero if any locale has at least one
      gap.
- [x] 2.4 Add a `--write` behavior consistent with the existing `--write` flag semantics: for each
      missing key, insert an entry using the English string as a placeholder value (never silently
      invent a translation) so a follow-up human pass can find and replace it.
- [x] 2.5 Update the script's usage/help output (if any) and `tests/l10n/README` or inline
      comments to document the new check.

## 3. Verify

- [x] 3.1 Run `npm run test:l10n` and confirm it now reports zero missing keys across all 36
      non-English locale files (or lists genuine pre-existing gaps in other locales, if any are
      found — resolve or file follow-up issues for those separately, do not silently skip them).
- [x] 3.2 Re-run `npm run test:l10n` a second time to confirm idempotency (no false positives on
      a clean tree).
- [x] 3.3 Confirm `l10n/nl.json` loads correctly in a running dev instance (admin settings page in
      Dutch locale shows the 3 previously-English strings translated).
      Owed live (2 Oct 2026): the shared instance was not used. The `.js` catalogue is
      regenerated and `check:l10n-js` passes. The dropdown label is still looked up in the old
      `nldesign` domain at `js/admin.js` (`updateTriggerLabel`); the domain move belongs to the
      issue that renames the l10n domain, and the Dutch label shows once that lands.

## Notes from the build (2 Oct 2026)

- Tasks 2.1-2.5 shipped as a sibling script, `tests/l10n/check-l10n-completeness.js`, rather
  than a second pass inside `check-l10n.js`. It counts a key with an empty value as missing,
  and `npm run test:l10n` (the script CI runs) now runs it after the extraction check.
- The Dutch file had grown from 94 to 522 keys by then; all 36 locales carry every key.
