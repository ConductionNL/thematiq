---
kind: mixed
---

# Proposal: rename-nldesign-to-themiq

## Summary

Rename the app from `nldesign` to `themiq`, under the fleet's `-iq` product
convention (pipelinq, shillinq, scholiq, portaliq, hermiq). The app id, the
`OCA\Thematiq` namespace, the appstore identity and every fleet reference move
together.

Off-chain from `hydra/openspec/changes/portaliq-phase-two`: nothing in that
chain blocks on it, and it blocks nothing. ADR-086 §6 makes Portaliq depend on
this app for corporate themes and states that until this change lands,
"themiq" and "nldesign" denote the same app.

## Motivation

The app manages corporate themes: token sets, per-app and per-group theming,
custom fonts, dark mode, theme preview, email theming — 32 capability specs.
Its name says "NL Design System", which is one of the token sets it ships, not
what it does. Every other product in the fleet named for its job carries the
`-iq` suffix.

This is a naming change, not a capability change. It is written down because a
rename of this size done informally leaves half the fleet on the old id.

## Affected Projects

- [ ] `nldesign` → `themiq` — app id in `appinfo/info.xml`, the
      `OCA\Thematiq\*` namespace, `package.json`, CI workflows, l10n (37 files),
      release config, docs.
- [ ] The fleet — **468 files outside this repo name the app id**, measured
      2026-08-15. Every one is a caller that breaks silently if it is missed.
- [ ] `portaliq` — takes a hard dependency on the renamed app (ADR-086 §6).

## What makes this risky, and it is not the code

A Nextcloud app id is not a label. It is:

- the directory name under `custom_apps/`
- the appstore identity, so a rename is a **new app** to the appstore, not an
  update to an existing one
- the key for `oc_appconfig` rows, so per-instance configuration does not
  follow the rename by itself
- the prefix on every `Util::addScript` / `addStyle` call and every
  `loadState` channel
- the l10n domain, so 37 translation files change domain at once

An installed instance therefore does not "get renamed". It gets a new app
installed alongside the old one, unless a migration moves the configuration
across and the old app is disabled deliberately.

## Design notes

**The config migration is the change, not the sed.** A repair step copies
`oc_appconfig` rows from `nldesign` to `themiq` and reports how many it moved.
A rename that leaves every instance's theme configuration behind produces a
fleet of correctly-named apps that all render the default theme — and each
deployment discovers that separately, in production.

**A transition alias, not a hard cut.** For one release the old app id resolves
to the new app so a consumer that still asks for `nldesign` keeps working, and
a deprecation warning names the caller. Removal is its own change, written at
the same time as this one — the same discipline `cms-proxy-removal` follows,
for the same reason.

**Count the callers before and after.** 468 files is the measured number today;
the change records it, and the verification re-counts rather than assuming the
sweep was complete.

## Risks

- **A missed caller fails silently.** `Util::addScript('nldesign', …)` for an
  app id that no longer exists produces a 404 on the asset and no PHP error —
  the page renders unstyled, which reads as a CSS bug.
- **Appstore discontinuity.** Existing installations do not auto-upgrade across
  an id change. The upgrade path has to be written for operators, not inferred.
- **Theme configuration loss.** See the config migration above. This is the
  failure that would be discovered per deployment rather than in CI.
- **Half-renamed is worse than not renamed.** Two ids in the fleet means every
  future reference is a coin flip.

## Out of scope

Any change to what the app does. Token sets, theming behaviour and the NL
Design System integration are untouched — the app keeps shipping NL Design
tokens, it simply stops being named after them.
