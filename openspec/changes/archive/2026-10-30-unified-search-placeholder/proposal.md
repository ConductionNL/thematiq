# Proposal: unified-search-placeholder

## Why

The DqKop board draws the top bar's search field with the app's own words: "Zoek zaken, documenten of contacten". On
:8080 the field reads "Search apps, files, tags, messages …", in English on a Dutch instance. That is a gap in
Nextcloud 34.0.0's core catalogue: the field asks for `Search apps, files, tags, messages …` (with the ellipsis), while
`core/l10n/nl.json` of 34.0.0 only translates the string without it. Later server branches carry the translation. Either
way the words are Nextcloud's, not the app's, and no app could change them.

## What changes

- `SearchPlaceholderService` finds the words for the app being rendered: the theme's entry for that app in
  `search_placeholders` (thematiq app config, JSON map of app id to text, `*` for every other app), else the app's own
  `search_placeholder` value in its own app config namespace (an app can declare it as a config lexicon default), else
  the theme's `*` entry. The text is an English source string translated through the app's catalogue (thematiq's for
  `*`).
- It runs as one more independent layer of `CssInjectionService::inject()`, provides the words as initial state and
  loads `js/search-placeholder.js`.
- The script puts the words in the collapsed field's label, the open field's placeholder and the search dialog's field
  label, and puts them back when the search re-renders.
- With no words anywhere nothing is injected. Fails open: a failure is logged and the field keeps Nextcloud's words.

Setting it as an administrator: `occ config:app:set thematiq search_placeholders --value='{"dossiq":"..."}'`.
