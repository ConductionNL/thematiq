---
sidebar_position: 1
---

# Token Sets

Thematiq includes **39 token sets** — pre-configured themes for Dutch government organizations. Each token set defines colors, typography, border radius, and other design tokens specific to that organization.

## Available Token Sets

| Organization | Primary Color | Logo |
|---|---|---|
| **Rijkshuisstijl** (National Government) | `#154273` | Yes |
| **Gemeente Amsterdam** | `#004699` | Yes |
| **Gemeente Bodegraven-Reeuwijk** | `#0066CC` | |
| **Gemeente Borne** | `#003352` | |
| **Gemeente Buren** | `#D41422` | |
| **Demodam** | `#03A9F4` | |
| **Gemeente Dinkelland** | `#006CB9` | |
| **Gemeente Drechterland** | `#1B6E8C` | Yes |
| **Gemeente Duiven** | `#1D5B8F` | |
| **DUO** | `#004FA3` | |
| **Gemeente Enkhuizen** | `#0055AD` | |
| **Gemeente Epe** | `#00549E` | Yes |
| **Gemeente Groningen** | `#154273` | |
| **Gemeente Haarlem** | `#1457A3` | |
| **Gemeente Haarlemmermeer** | `#068E8C` | |
| **Gemeente Hoorn** | `#09366C` | Yes |
| **Gemeente Horst aan de Maas** | `#125EA4` | |
| **Gemeente Leiden** | `#d62410` | Yes |
| **Gemeente Leidschendam Voorburg** | `#1E1B54` | |
| **Gemeente Nijmegen** | `#157C68` | Yes |
| **Noaberkracht** | `#4376fc` | |
| **Gemeente Noordoostpolder** | `#389003` | |
| **Gemeente Noordwijk** | `#2C2276` | Yes |
| **Provincie Zuid-Holland** | `#C42035` | Yes |
| **Riddeliemers** | `#154273` | |
| **Ridderkerk** | `#008937` | |
| **Gemeente Stede Broec** | `#035935` | |
| **Gemeente Tilburg** | `#003366` | Yes |
| **Gemeente Tubbergen** | `#067432` | |
| **Gemeente Venray** | `#2A8113` | |
| **Gemeente Vught** | `#0088AD` | |
| **VNG** (Vereniging Nederlandse Gemeenten) | `#003865` | Yes |
| **Gemeente Westervoort** | `#003C6B` | |
| **Gemeente Utrecht** | `#24578F` | Yes |
| **Gemeente Den Haag** | `#1a7a3e` | Yes |
| **Gemeente Rotterdam** | `#00811f` | Yes |
| **xxllnc** | `#333333` | Yes |
| **Gemeente Zevenaar** | `#596E28` | |
| **Gemeente Zwolle** | `#3A4F93` | |
| **(EXAMPLE) Basisschool** (fictional) | `#B03A12` | Yes |
| **(EXAMPLE) Voortgezet onderwijs** (fictional) | `#2D4FA0` | Yes |
| **(EXAMPLE) College** (fictional, mbo) | `#7A1F6E` | Yes |
| **(EXAMPLE) Opleider** (fictional) | `#0E4D5C` | Yes |

## Token Set Sources

Token sets are sourced from the official [NL Design System themes repository](https://github.com/nl-design-system/themes) and individual organization design systems. The token generation script (`scripts/generate-tokens.mjs`) translates upstream design tokens into the `--nldesign-*` CSS variable namespace.

## Adding a New Token Set

To add support for a new organization:

1. Create a CSS file at `css/tokens/{id}.css` with all required `--nldesign-*` variables
2. Add metadata to `token-sets.json` (name, description, primary color)
3. Optionally add a logo SVG at `img/logos/{id}.svg`

The admin dropdown picks up new token sets automatically — no PHP changes needed.

For detailed information on the token variable namespace, see the [Token Architecture reference](../reference/tokens).

A leaf app's own picker (a builder tool inside another Conduction app, not
the admin dropdown above) can enumerate this same catalogue and evaluate
WCAG contrast without admin privileges via a small, closed, non-admin API —
see the [App Token-Set Selection contract](../reference/app-token-scope-contract.md)
for the endpoints, response shapes, and the scoped-application contract a
shared client-side applier implements against.
