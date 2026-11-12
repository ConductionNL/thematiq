---
kind: code
---

# Proposal: token-references-stay-live

## Summary

Row `aut-semantic-aliases` says an administrator defines tokens as references to other tokens, so one change flows through the semantic layer. Today a reference lives only for the length of a DTCG import: `DesignTokensAliasResolver` resolves `{token.path}` and `DesignTokensMapper` writes the concrete value into the stored set. The token editor and `custom-overrides.css` hold flat values, so editing a token later changes nothing that referred to it in the source file. The row is set back to `specified` until this change lands.

## What changes

- ADDED `token-editor-ui` `A token can refer to another token`: the editor accepts a reference to another `--nldesign-*` token as a value, the stored overrides keep it as `var(--...)`, a later edit of the target reaches every token that refers to it, and a cycle or a missing target is refused.
- The DTCG import keeps an alias whose target is a mapped token as a `var(--...)` reference instead of the resolved value.

## Screens

TqComponenten (the token editor rows). See `screens.md`.
