---
kind: code
---

# Token sets go to Figma and come back

## Why

Ruben asked on 13 Oct 2026 that thematiq and the OpenProject plugin both take and give Figma token
files, so a designer changes a house style in Figma and both products follow. A file downloaded from
one product must upload into the other.

Thematiq already wrote a W3C Design Tokens document (`token-set-dtcg-export`) and read one back, and
it read a Tokens Studio file with two or more themes as several brands. Three things were missing.
There was no Tokens Studio download. A Tokens Studio file with one theme or none kept its token set
name in front of every path, so its references did not resolve, and the `value` and `type` spelling
lost every colour. And a thematiq file that went through Figma lost the extension that carried each
CSS name, so only eleven mapped paths came back.

## What changes

- The download takes a `format`: `dtcg` (as before), `figma`, `tokens-studio` and
  `tokens-studio-legacy`. The admin page has a list for it next to **Download as design tokens**.
- An upload with `$themes` or `$metadata` has its token sets merged in `tokenSetOrder` before it is
  mapped. The `value` and `type` spelling is read as `$value` and `$type`.
- A file whose tokens sit under `nldesign` gets its CSS names back from the paths.
- A reference cycle, a sum over references and a switched off token set are left out and reported
  in the upload result.
- `docs/features/figma-tokens.md` holds the path of every token in each format and the path of each
  of the eighteen shell roles. The plugin lane reads a copy.

## What does not change

A W3C document, a Style Dictionary tree, a CSS upload and thematiq's own download with its
extensions are handed to the converter byte for byte as before. The validator, the 512 KB limit and
the rule that the stored file is written from parsed values are the existing ones.

## Not in this change

A Tokens Studio folder with one file per token set. It needs an archive upload, which the token set
upload does not have.
