# Proposal: action-menu-links-keep-text-colour

## Why

In dossiq's case page "Acties" menu the last three items ("Een functie
aanvragen", "Een bug melden", "Documentatie") showed in the link colour, the
first of them underlined, while every other item read in the main text colour.
Those three are NcActionLink anchors (`a.action-link`); the button items are
not. Two nldesign rules reach an anchor and nothing else:

- element-overrides.css `a:not(#header a)...` forces
  `var(--nldesign-color-on-surface, var(--nldesign-color-link))` with
  `!important` at id level, over NcActionLink's scoped `color: var(--color-main-text)`;
- theme.css `a { text-decoration: var(--nldesign-component-link-text-decoration) !important }`.

## What changes

An action menu link item sets `--nldesign-color-on-surface` to the main text
colour (the opt-out the css-architecture spec prescribes for a surface with its
own ink) and drops the underline. It applies to every NcActions menu in every
app under an nldesign set.
