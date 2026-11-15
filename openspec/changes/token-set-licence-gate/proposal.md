# Token set licence gate

## Why

Thematiq ships house styles that belong to other organisations. Their source packages say who may use
them: several reserve all rights, and the Rijkshuisstijl forbids use except by parties on a commission
for the Rijksoverheid. Today nothing in the app carries that licence, and any administrator can apply
any of these sets with one click.

Ruben ruled on 12 October 2026 (decisions 232 and 237): the proprietary sets are included, with their
licence and a warning, and they are off until confirmed. An administrator states "we are this
organisation or have its permission" before such a set can be applied, and the statement is logged.

## What changes

- A shipped token set can declare its licence in `token-sets.json`: an identifier, the source package
  and its version, the holder, and whether use is limited to the owner. The licence text ships beside
  the set as `css/tokens/<id>.LICENSE.md`.
- A set whose licence limits use to its owner is restricted. The server refuses to apply it until an
  administrator confirmed that set. The refusal is an HTTP 403, on every path that applies a set.
- The confirmation is kept per set, with who and when, and written to the theming audit trail and the
  Nextcloud log.
- The admin settings list every restricted set with its warning, its licence text and the confirmation.
- A confirmation can be withdrawn once the set is applied nowhere.
- A set that was applied before it became restricted stays applied. The settings page asks for the
  confirmation.

This change is the mechanism only. It marks no shipped set as restricted. The audit of the shipped
sets and the seven empty sets follow as their own changes.

## Impact

- New: `RestrictedSetGate`, `TokenSetLicenceService`, `TokenSetLicenceController`, two exceptions,
  `js/admin-set-licence.js`.
- Touched: every service that applies a set (the instance-wide switch, the command line, group
  mappings and delegated choices, previews, planned switches, brands per app, configuration bundles).
- No behaviour changes on upgrade: no shipped set declares a restricted licence yet.
