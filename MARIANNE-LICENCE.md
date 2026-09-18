# Marianne Font — Licence and Restriction

**Marianne is the official typeface of the French State — réservée aux
administrations de l'État (reserved for French State administrations).**
This document states the licence the bundled font files ship under and the
restriction on who may use them. See also
[AGREEMENT-MARIANNE.md](AGREEMENT-MARIANNE.md) for the operator agreement
that governs enabling Marianne in this app.

## What is bundled

`css/systems/lasuite/fonts/marianne/` contains 8 `woff2` files (Light,
Regular, Medium, Bold, each with an italic variant), sourced from
[`@gouvfr/dsfr@1.15.1`](https://github.com/GouvernementFR/dsfr) (npm
package `@gouvfr/dsfr`, `dist/fonts/Marianne-*.woff2`) — the official
French government Design System (**DSFR**), published by
[**Etalab**](https://www.etalab.gouv.fr/), the French Prime Minister's
open-data/open-source mission.

Source of the DSFR package and the official system-de-design site:

- npm: <https://www.npmjs.com/package/@gouvfr/dsfr> (version 1.15.1)
- Repository: <https://github.com/GouvernementFR/dsfr>
- Design system site: <https://www.systeme-de-design.gouv.fr/>

## Licence: Etalab Open Licence 2.0

The DSFR package — including the Marianne font files — is published under
the **Etalab Open Licence 2.0** (`Etalab-2.0`, "Licence Ouverte 2.0" /
"Open Licence 2.0"). The full licence text is reproduced at
[`LICENSES/etalab-2.0.txt`](LICENSES/etalab-2.0.txt).

Official licence page: <https://www.etalab.gouv.fr/licence-ouverte-open-licence/>

The Etalab Open Licence 2.0 permits free reuse, reproduction, redistribution,
and commercial exploitation, subject to attribution. **It does not, by
itself, restrict who may use the DSFR's contents** — but Marianne carries an
**additional, separate usage condition** below, layered on top of the
licence.

## The Marianne restriction (verbatim)

`@gouvfr/dsfr@1.15.1`'s own `LICENSE.md` states, verbatim (French, with an
English translation):

> Le code du DSFR est sous licence Etalab 2.0, mais son utilisation est
> encadrée par des modalités d'utilisation. En raison de son rôle de
> marqueur d'identité visuelle de l'État, le DSFR ne doit pas être utilisé
> par des entités extérieures à l'administration, et limite sa réplicabilité
> en dehors d'un nom de domaine en .gouv.fr. En cas d'usage à des fins
> trompeuses ou frauduleuses, l'État se réserve le droit d'entreprendre les
> actions nécessaires pour y mettre un terme.
>
> *(English: The DSFR code is licensed under Etalab 2.0, but its use is
> governed by terms of use. Because of its role as a visual identity marker
> of the State, the DSFR must not be used by entities outside the
> administration, and its replicability is limited outside a `.gouv.fr`
> domain. In case of misleading or fraudulent use, the State reserves the
> right to take the necessary actions to put an end to it.)*

This is corroborated by
[`suitenumerique/meet#426`](https://github.com/suitenumerique/meet/issues/426)
("Marianne font compatible with MIT license?"), which records that Marianne
"cannot be proposed as is for self-hosters" outside the French State
administration context.

**In plain terms: Marianne is reserved for French State administrations
("administrations de l'État").** It is not a general-purpose open font —
using it to represent an organisation as, or visually associate it with,
the French State when it is not one is exactly the misuse this restriction
exists to prevent.

## How this app complies

The restriction on Marianne is a condition on **who may use the font**, not
a bar on **redistributing the files** — the Etalab Open Licence 2.0 permits
redistribution, including by a third party like Conduction, to organisations
entitled to use the contents. Conduction bundles Marianne specifically
because it serves French government customers who are themselves French
State agencies, and for whom self-hosting Marianne (rather than relying on
it being installed at OS level, which is what the un-gated fallback did
before this change) is a legitimate, entitled use.

To prevent silent misuse on any instance whose operator is *not* a French
State agency, this app:

1. **Ships Marianne inert by default.** The font files are bundled, but no
   `@font-face` `url()` source for Marianne exists until an admin
   deliberately enables it — see the *"Marianne Activation Requires an
   Admin Acknowledgement Gate"* requirement in
   `openspec/specs/marianne-font/spec.md`.
2. **Requires an explicit admin acknowledgement.** The admin settings page
   presents an unmissable restriction notice next to a checkbox — *"Our
   organisation is a French State agency (administration de l'État)"* —
   which must be ticked before Marianne activates. See
   [AGREEMENT-MARIANNE.md](AGREEMENT-MARIANNE.md) for what ticking it means.
3. **Reverts cleanly.** Unticking the checkbox reverts every subsequent
   render to the self-hosted, unrestricted Inter typeface, without deleting
   the bundled Marianne files.
4. **Carries this licence and attribution with the files.**
   `.license-overrides.json` maps every bundled Marianne `woff2` path to the
   `Etalab-2.0` SPDX identifier, resolving to
   [`LICENSES/etalab-2.0.txt`](LICENSES/etalab-2.0.txt).

## Copyright

Marianne and the DSFR are © the French State / Etalab. This document and
the accompanying licence file do not grant any rights beyond what the
Etalab Open Licence 2.0 and the restriction above already state.
