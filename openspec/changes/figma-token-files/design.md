# Design: figma-token-files

## Two formats, read from their own documentation

- **W3C Design Tokens, Format Module 2025.10** (designtokens.org/tr/2025.10/format): `.tokens.json`,
  `$value`, `$type` inherited from the group, `{group.token}` references, names without `{`, `}`
  or `.`, colour as an object, dimension as `{value, unit}` in `px` or `rem`.
- **Figma variables.** Figma's own sample (`figma/plugin-samples`, `variables-import-export`)
  writes one `.tokens.json` per collection and mode, splits a variable name on `/` into groups,
  writes `color` as `#rrggbb` or `rgba()` text and `number` as a number, and on import takes only
  `color` and `number`. It does not read the 2025.10 colour object. Hence the `figma` format.
- **Tokens Studio for Figma** (docs.tokens.studio, and the plugin's `ThemeObject` and
  `SystemFilenames` types): a single file holds each token set under its own key plus `$themes`
  and `$metadata`. A theme has `id`, `name`, an optional `group` and `selectedTokenSets` with
  `enabled`, `source` or `disabled`. The W3C spelling only puts `$` before `value`, `type` and
  `description`; the type names stay Tokens Studio's (`fontFamilies`, `fontWeights`). Its
  documentation does not describe `$metadata.tokenSetOrder`; the plugin source and the existing
  `MultiBrandSource` do.

## The path is the name

`DesignTokensWriter::pathOf()` cuts a CSS name at its first two dashes. Joining the segments with
dashes gives the name back, so the path alone carries it. `FigmaTokenReader` uses that for a file
whose tokens sit under `nldesign` and that has no thematiq set id: a colour stays a token and gets
its name in the extension, any other plain value is the CSS value and goes to the `cssOnly` map the
mapper already reads. The document then carries a set id, so the converter keeps every name and
fills in nothing.

## Hostile input

The reader moves values and decides nothing about safety. Every value still passes
`DesignTokensMapper` and then `CustomTokenSetValidator` on the file that would be stored. The reader
adds three refusals of its own, each reported: a reference cycle, a value with a brace left in it,
and a boolean or empty value. Depth is capped at 16.

## Where it hooks in

`TokenSetConverterService::convert()` calls the reader first, so the admin upload, the brand import
and the command line all get it. `DtcgExportController::export()` calls `FigmaTokenWriter` after
`DesignTokensWriter`.

## The plugin's eight differences

Closed here: the `text` value has a name and the role reads it (1); a light and dark Tokens Studio
file is one house style (4, the light half); a path without the extension is read by joining it
(5); the `value` and `type` spelling is read (7). Kept but not a role: `menu` (2). The endpoint has
eighteen roles at version 1, and a nineteenth is a change to that contract, not to a file format.
Left open: dark colours from a file (3, and the dark half of 4). Thematiq generates the dark variant
of a set from its light colours and regenerates it when the set changes, so a dark colour read from
a file has nowhere to stay; that needs dark overrides per set, a feature of its own. The original
text of a colour (6) lives in the plugin's extension and only changes the notation. The derived
values of a partial file (8) are thematiq's as they were; the page now lists each rule and its
weight so the plugin can follow them.
