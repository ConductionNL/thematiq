---
kind: code
---

# Let another product read the active shell tokens

## Why

A themed Nextcloud has a recognisable shell: a header in the house colour, the house logo, buttons,
links and panels in the same few colours. An organisation that runs OpenProject next to Nextcloud
wants OpenProject to wear that shell too. `ConductionNL/openproject-thematiq` is the plugin that
does it, and it needs the active house style as data.

Thematiq serves the house style as stylesheets for a Nextcloud page. It has no route another server
can read:

- `GET /api/token-sets` lists every set, for signed-in users only (`CatalogController`,
  `#[NoAdminRequired]`). It does not say which set is active and holds two colours per set.
- `GET /settings/tokensets/{id}/dtcg` is the whole set as design tokens, for administrators only
  (`DtcgExportController`).
- The capabilities document names the active set, with no values.

OpenProject is a server without a Nextcloud session. Giving it a Nextcloud account and an app
password to read a colour would hand it far more than it needs.

## What changes

- A new route, `GET /apps/thematiq/api/shell-tokens`. It answers with the active set as eighteen
  shell roles (colours, two radii, a font stack), the set's logo, and a revision.
- The route is public and rate limited, like the font, icon and runtime file routes. It holds theme
  values only.
- The answer carries an `ETag`. A reader that sends it back in `If-None-Match` gets `304`.
- `ShellRoleMapper` maps a token set onto the eighteen roles. Every role always has a value.

## Impact

- Nothing an administrator or a visitor sees changes. No setting, no migration.
- A new public read. Everything in it already reaches any visitor as CSS on the login page. See
  design.md, decision 1.
- A new path. An instance that is already serving requests answers 404 on it until its route cache
  is renewed. See design.md, decision 5.
- No screen: the route answers JSON to another server.
