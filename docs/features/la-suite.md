---
sidebar_position: 27
---

# La Suite design stack

Run Nextcloud next to the French La Suite numérique apps without a visible seam. The La Suite stack gives Nextcloud the type ramp, palette, radii and surfaces of Docs, Meet and Chat. That matters in sovereign-workplace bundles where Nextcloud sits among those apps.

## Pick a La Suite set

Three token sets use this stack:

| Token set | Id | What it is |
|---|---|---|
| La Suite numérique | `lasuite` | The violet brand that La Suite apps deploy |
| La Frankendesk | `frankendesk` | A variant that builds on `lasuite` |
| Cunningham (blue base) | `cunningham` | The published blue base of the Cunningham design system |

Pick one in **Design token set** in the Thematiq admin settings, or from the command line:

```bash
occ thematiq:theme:set lasuite
```

## Where the tokens come from

The defaults layer is generated from the Cunningham token package, `@openfun/cunningham-tokens`, under the MIT licence. It holds every Cunningham token as a `--lasuite--*` custom property. A bridge maps them onto the `--nldesign-*` names the rest of Thematiq reads, so the token editor, contrast checks and dark mode work as for any other set.

The token reference for each set is in the [La Suite reference](../reference/token-sets/lasuite.md) and the [Cunningham reference](../reference/token-sets/cunningham.md).

## Fonts: Inter by default, Marianne only for the French State

La Suite apps set their font stack as Marianne first, then Inter. Thematiq self-hosts Inter, under the SIL Open Font License, and uses it by default.

Marianne is the official typeface of the French State. It is bundled, self-hosted, under the Etalab Open Licence 2.0, taken from the `@gouvfr/dsfr` package. Its use is reserved for French State administrations. So it is off by default, and the browser fetches no Marianne file until you switch it on.

To switch it on, select a La Suite set. Then tick **Our organisation is a French State agency (administration de l'État)**. Ticking it is your statement that your organisation is eligible. Only do so when that is true.

The terms are in `MARIANNE-LICENCE.md` and `AGREEMENT-MARIANNE.md` in the app.

## Icons

The `lasuite` design system declares the French government DSFR icon pack. The admin settings show the active pack under **Active icon pack**. See [icons](../reference/icons.md) for how apps use it.

Next, compare the result against a La Suite app open in a second tab.
