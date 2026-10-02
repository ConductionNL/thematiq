# Multi-brand fixtures (authoring-multi-brand-token-source)

Hand-authored, no upstream package, as `nlds-theme-converter` decision 3.

| File | Shape |
|---|---|
| `three-themes.tokens.json` | Tokens Studio, DTCG `$value` form: token sets `core` (source in every theme), `common`, and one set per brand; `$themes` with Noord, Zuid and Oost; `$metadata.tokenSetOrder`. Noord lists `brand/zuid` as disabled. |
| `three-themes-legacy.tokens.json` | The same in the Tokens Studio legacy `value`/`type` form, with literal values. |
| `two-brand-classes.css` | Built theme CSS: a shared `:root`, `.noord-theme` and `.zuid-theme`, and a `.noord-theme--dark` modifier that is not a brand. |
