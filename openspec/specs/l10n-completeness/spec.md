# l10n-completeness Specification

## Purpose
Every shipped locale carries every English source key with a non-empty value, and the l10n gate that CI runs fails when one does not. Created by archiving change l10n-locale-completeness-check.

## Requirements

### Requirement: Every Shipped Locale Covers Every English Source Key

Every key present in `l10n/en.json`'s `translations` object MUST also be present, with a non-empty string value, in every other `l10n/<locale>.json` file shipped by the app (`l10n/en.json` itself is the i18n source of truth and is exempt). A key present in `en.json` but absent, or present with an empty string, in another locale file constitutes a silent English-fallback regression for that locale's users.

#### Scenario: Dutch locale has translations for all app-theming dropdown strings

@e2e exclude the strings are catalogue data, not page behaviour; proven by tests/vitest/l10nCompleteness.spec.js 'translates the app-theming dropdown strings into Dutch'

- GIVEN `l10n/en.json` contains the keys `{themed} of {total} apps themed`, `Search apps`, and
  `Search apps…` (used by the per-app theming dropdown in `js/admin.js`)
- WHEN `l10n/nl.json` is inspected
- THEN all three keys MUST be present with non-empty Dutch translations
- AND a Dutch-locale admin viewing the per-app theming panel at `/settings/admin/theming` MUST see
  no English strings on that screen

### Requirement: The l10n Check Tool Catches Cross-Locale Gaps

`tests/l10n/check-l10n.js` MUST verify that every key in `l10n/en.json` exists, with a non-empty value, in every other `l10n/*.json` file, in addition to its existing "every key used in `js/**` exists in `l10n/en.json`" check. It MUST exit non-zero when any locale is missing at least one key.

#### Scenario: Check tool fails when a locale is missing a key present in en.json

@e2e exclude a build-time check with no page; proven by tests/vitest/l10nCompleteness.spec.js 'fails and names the key and locale when a locale lacks an en.json key' and 'runs the cross-locale completeness check, so CI catches a gap'

- GIVEN a key exists in `l10n/en.json`'s `translations` object
- AND that same key is absent from `l10n/nl.json`'s `translations` object
- WHEN `npm run test:l10n` (`L10N_SRC_DIR=js node tests/l10n/check-l10n.js`) is run
- THEN the process MUST exit with a non-zero status code
- AND the output MUST name the missing key and the locale file it is missing from

#### Scenario: Check tool passes when every locale has every en.json key

@e2e exclude a build-time check with no page; proven by tests/vitest/l10nCompleteness.spec.js 'passes and reports the locale count when every locale is complete'

- GIVEN every key in `l10n/en.json` has a corresponding non-empty entry in every other shipped
  `l10n/*.json` file
- WHEN `npm run test:l10n` is run
- THEN the process MUST exit 0
- AND the output MUST report the number of locales checked and confirm completeness
