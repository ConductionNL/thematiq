---
kind: code
---

# Proposal: rows-without-a-requirement

## Summary

Seven capability rows in `openspec/parity/capabilities.json` cite a spec dir whose requirements do not say what the row says. Six are built and only lack the requirement. One, `typ-org-font-library`, is not built: the font list holds uploads only, with no library of approved fonts to pick from. This change writes one requirement per row in the main spec the row belongs to, so each row can record a requirement that resolves.

## The rows this covers

- **cat-community-sets** Use community-maintained brand sets for organisations that are not a municipality. (`token-sets`)
- **app-login-preview** Preview how the login page will look before publishing. (`component-playground`)
- **typ-org-font-library** Offer a central library of approved organisation fonts to choose from. (`custom-fonts`, not built)
- **gov-audit-append-only** Rely on the audit log not being editable after the fact. (`theming-audit`)
- **int-tokens-for-other-apps** Let other apps style themselves with the same CSS variables, with no build step. (`css-architecture`)
- **int-no-js-overhead** Apply the theme as pure CSS with no JavaScript cost on ordinary pages. (`css-architecture`)
- **sur-selection-colour** Have selected and highlighted text take the brand colour in a readable, translucent form. (`nextcloud-variable-mapping`)

## What changes

1. One ADDED requirement per row, each with at least one scenario.
2. `@spec` tags and unit tests that pin the six built behaviours.
3. A build task for the approved font library.
4. The seven rows record their requirement in `built.requirement`; `typ-org-font-library` moves from `built` to `planned`.

## Screens

None new. The login preview and the font list are already drawn on TqComponenten and TqTheming. See `screens.md`.

## Out of scope

- Opt-in page features that ship their own script (workplace header, apps menu, search wording, login and not-found copy, environment marker). They are specified in `workplace-layout` and `environment-marker`; `int-no-js-overhead` covers the theme itself.
