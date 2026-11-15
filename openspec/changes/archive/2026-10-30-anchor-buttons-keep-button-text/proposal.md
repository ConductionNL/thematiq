# Proposal: anchor-buttons-keep-button-text

## Why

On dossiq's Mijn werk page the "Vandaag eerst" card draws its actions as
buttons. "Open deze zaken" and "Naar het werkbord" showed blue underlined
text, "Zaak openen" white underlined text, while "Termijn opschorten" beside
them (a real `<button>`) read correctly. The first three are anchors drawn as
buttons (CnBannerWidget actions with a route), and the link rules reach every
anchor:

- theme.css `a { text-decoration: var(--nldesign-component-link-text-decoration) !important }`
  and the link, hover and visited colour rules;
- element-overrides.css `a:not(#header a)...` forcing
  `var(--nldesign-color-on-surface, var(--nldesign-color-link))` at id level;
- high-contrast theme.css `a` and `a:hover` (black, underlined), and the
  forced-colors `LinkText` rule.

Text in a button is never underlined, and a link drawn as a button never takes
the link colour.

## What changes

The link rules leave out an anchor that is a button: `a.button-vue` (NcButton
with `href` or `to`), `a.button` (core), `a[role="button"]`,
`a.cn-banner-widget__action` and `a.cn-link-button-widget__button`. It keeps
its button's own text colour for every variant, and a class-level rule sets
`text-decoration: none !important` in every state. Under forced colours it
takes `ButtonText`. The exclusions use `:where()`, so no other rule's
specificity changes.
