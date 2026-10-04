---
sidebar_position: 16
---

# Public portals as consumers

These docs describe theming **the Nextcloud UI** — the interface an authenticated
user sees. There is a second consumer, and it behaves differently in ways that
have already caused real defects: a **public portal**, rendered by
[portaliq](https://github.com/ConductionNL/portaliq) for anonymous visitors on a
government website, with no Nextcloud chrome and no session.

A portal reads four things from this app. What follows is what each one has to
survive on that surface.

## 1. The token-set catalogue

A portal resolves its `theme` against `token-sets.json`, **read from disk** —
not through `/api/token-sets`.

That endpoint is `#[NoAdminRequired]` and deliberately not `#[PublicPage]`; its
own docblock records that exposing admin-uploaded custom sets to anonymous
traffic would be an information-disclosure surface with no consumer need. A
public renderer serving anonymous visitors must not become that consumer, so it
reads the same file the endpoint reads.

**A set present on disk but absent from the catalogue is not adoptable**, and a
theme that does not resolve renders the portal **unstyled** rather than falling
back. A municipality quietly wearing another municipality's colours renders
perfectly and is invisible to a screenshot.

## 2. The public bridge

A shipped token set is a Layer-3 `--nldesign-*` override list — correct for
theming Nextcloud, and **inert on a portal**, because the page paints from the
vendored `--utrecht-*` / `--tilburg-*` component roles.

Measured with the real `frankendesk` set applied: its violet resolved perfectly
at `--nldesign-color-primary` while `--utrecht-document-color` was unset and the
header painted transparent. The theme loaded and did nothing.

`public-bridge.css` maps one family onto the other and is meant to be linked
**before** the set. Anything a portal needs that has no `--utrecht-*` role has
to travel through that bridge, or it reaches nothing.

**Portaliq does not link it yet.** Its open change `site-links-the-theme-bridge`
adds the link. Until then a portal paints from whatever the set itself declares.

Measured coverage of the listed sets (`token-sets.json`, 54 sets):

| What a set declares | Sets |
| --- | --- |
| the full `--utrecht-*` role layer | 22 |
| only `--nldesign-*` | 11 |
| Den Haag component properties | 14 |
| a case card property | 0 |

### The Den Haag components

Portaliq's mijn-omgeving components use the `@gemeente-denhaag/*` CSS. That CSS
reads `--denhaag-*` and `--nl-data-badge-*` properties, mostly without
fallbacks. The bridge's generated Den Haag section gives every one of them a
value for every set: colours follow the set's `--nldesign-*` layer, geometry is
Den Haag's own. The rules live in `scripts/mapping/denhaag-component-tokens.json`;
`npm run generate:denhaag-bridge` writes the section and
`npm run test:denhaag-bridge` fails when it is stale. A set that declares a Den
Haag property itself keeps it, because the bridge loads first.

## 3. The generated dark variants — currently unusable on a portal

A portal does **not** link `css/tokens/dark/<id>.css`, and the reason is a
defect in the artefacts rather than a choice:

| | light | dark |
| --- | --- | --- |
| surfaces that changed | — | **0 of 8** |
| text below AA | 0 of 38 | **19 of 38** |
| worst | — | **1.03:1** |

**An alias resolves where it is DECLARED.** The generated file scopes its
overrides to `body` and redefines base colours there, while the light set
declares the aliases above them on `:root`. Read back live in a dark-scheme
browser:

```
--c-white                              root #FFFFFF   body #141414
--conduction-color-background-default  root #FFFFFF   body #FFFFFF
--utrecht-document-background-color    root #FFFFFF   body #FFFFFF
```

So the surfaces stay white while the direct text colours darken — which is how
a footer reaches 1.03:1. No change on the consumer's side fixes it: the chain
breaks at its first `:root`-declared link whichever end it is read from.

**For a dark variant to work on a public portal, the generated set must
redefine the ALIASES** — or scope its block to `:root` inside the media query.
That is defect 1 of #353, recorded as fixed in the generator but not present in
the artefacts currently shipped.

## 4. Fonts, and what is NOT a font problem

`FontController::css()` is `#[PublicPage]`, so a portal links it directly for
**administrator-uploaded** faces. That works and needs nothing further.

It does **not** replace a consumer's own re-declaration of the vendored design
system's faces. Those captured stylesheets carry root-relative `url()`s that
resolve against the origin — measured from a consumer's path: four requests,
four 404s, `document.fonts` reporting `Roboto 400/500/700 error`, and the whole
portal drawn in Arial. That is a base-URL problem, not a font-management one.

## 5. Shared theme configuration

`NlDesignThemeShareableConfigType` and `CustomTokenSetValidator` are consumed
together: a portal **copies** an adopted set into its own tokens rather than
linking it.

A link would mean another instance can change, or remove, what a live
government portal looks like at a moment nobody there chose. Copying makes
adoption a decision with a result, and a withdrawal upstream something an
operator is told about rather than something that happens to their visitors.

Every declaration goes through `CustomTokenSetValidator` before it can be
stored, and a `null` return is treated as a **hard refusal** rather than as
"nothing to adopt" — the two are indistinguishable to a caller that only checks
whether the accepted list is empty.

## What a portal adds that this app cannot know

Two things, and they belong on the consumer's side:

- **Which portal is being served** — resolved by host, then by explicit slug.
- **Which of its own surfaces need a token.** A portal's page, cards and bands
  are painted by vendored component CSS with literal colours; until the
  consumer routes each one through `--utrecht-document-*`, a theme can be
  perfect and change nothing. Eight surfaces on that renderer were in exactly
  that state.
