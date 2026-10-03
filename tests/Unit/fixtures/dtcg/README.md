# DTCG import fixtures

Fixtures for `DesignTokensMapperTest` covering the W3C DTCG Format Module
v2025.10 ingestion contract (`openspec/specs/custom-token-sets/spec.md`).

## Synthetic corpus (01–13)

| File | Exercises |
| --- | --- |
| `01-legacy-scalar-regression.tokens.json` | Regression — identical to the pre-hardening mapper's own test document (own `$type` on every token); must still import 2/2, 0 skipped, 0 errors. |
| `02-group-type-inheritance.tokens.json` | Group-level `$type` inherited by an untyped descendant token. |
| `03-object-color-and-dimension.tokens.json` | v2025.10 object-form `color` (sRGB + hex, imports) and `dimension` (`{value, unit}`, imports), plus an object-form color in a colour space the DTCG colour module does not list (`cmyk`, `unsupported-color-space`). |
| `04-composite-typography.tokens.json` | Composite `typography` token: `fontFamily` array maps to `--nldesign-font-family`; `fontSize`/`fontWeight`/`lineHeight` have no target and are counted skipped with their sub-path. |
| `05-alias-chain-3-hop.tokens.json` | Transitive alias resolution across 3 hops to a concrete `color` leaf. |
| `06-alias-cycle.tokens.json` | `a.x <-> b.y` alias cycle (`alias-cycle`, full chain in the error) alongside an unrelated valid token — the valid token still imports. |
| `07-alias-dangling.tokens.json` | Alias referencing a path that does not exist (`alias-target-missing`). |
| `08-alias-depth-bomb.tokens.json` | 14-hop alias chain — exceeds the 10-hop bound (`alias-depth-exceeded`) before ever reaching the concrete leaf. |
| `09-deprecated.tokens.json` | `$deprecated` (string form) on an otherwise-valid token — imports AND surfaces a warning. |
| `10-extensions-heavy.tokens.json` | Nested `$extensions` at document, group and token level, including one with a `$value`-shaped key inside it — must never be read as a leaf (passthrough-ignore) while the sibling real token still imports. |
| `11-version-carrying.tokens.json` | Top-level `$version` recorded verbatim as `packageVersion`. |
| `12-zero-yield.tokens.json` | Well-formed, fully-typed document where no token matches any `--nldesign-*` target — `imported: 0` (controller-level 422 case). |
| `13-malformed.tokens.json` | Deliberately invalid JSON syntax (parse failure, controller-level 422). |

The `$extensions` version-convention and plain top-level `version` string
conventions (task 1.5's second and third precedence tiers) are covered by
inline document literals in `DesignTokensMapperTest` rather than dedicated
fixture files, alongside the literal 2-hop alias example from the canonical
spec scenario.

## Real municipal package excerpts (14–15)

Trimmed excerpts of real, published `@nl-design-system` npm token packages —
paths and values copied verbatim, only reduced in size. Both source packages
predate consistent DTCG `$type` adoption, which the excerpts preserve
faithfully (that gap is exactly what `missing-type`/`$extensions`-convention
handling in this change is for):

- **`14-utrecht-real-excerpt.tokens.json`** — from `@utrecht/design-tokens`
  v6.2.1 (`src/brand/utrecht/color.tokens.json`,
  `src/component/utrecht/heading-1.tokens.json`,
  `src/component/nl/heading.tokens.json`). The real package sets no `$type`
  anywhere; `$type: color` / `$type: fontFamily` group annotations were added
  here (on `utrecht.color` / `utrecht.typography`) to exercise group-type
  inheritance against genuine values — documented, not fabricated, since the
  upstream package itself omits `$type`. The `color.primary` semantic alias
  (`{utrecht.color.blue.35}`) mirrors the real brand→semantic bridging layer
  NL Design System themes build on top of primitives; `utrecht.color.blue.35`
  is the actual "basis link en knoppen CTA donkerblauw" (primary CTA colour)
  value from the shipped package. Expect 1 imported (`color.primary`), 8
  skipped (`unmapped-path` — none of the real nested paths coincide with our
  suffix table), 0 errors.
- **`15-amsterdam-real-excerpt.tokens.json`** — from
  `@amsterdam/design-system-tokens` v4.2.0
  (`src/brand/ams/color.tokens.json`, `src/brand/ams/typography.tokens.json`).
  Verbatim real values, including the real `$extensions`-based vendor typing
  convention (`nl.amsterdam.type` / `nl.amsterdam.subtype`) the Amsterdam
  package uses instead of `$type` for several typography tokens — a genuine,
  unmodified `missing-type` case. The one adaptation: the source
  `color.tokens.json` file declares `"$type": "color"` at its own file root
  (applying document-wide within that single-purpose file); merged into this
  combined excerpt it is nested onto the `ams.color` subtree it originally
  scoped, preserving identical effective inheritance without incorrectly
  cascading "color" onto the unrelated `ams.typography` subtree. Expect 1
  imported (`ams.typography.font-family`, a real array-form `fontFamily`
  token), 10 skipped (`unmapped-path`), 2 errors (`missing-type`, for
  `hyphenate-limit-chars` and `italic.font-style`) — 13 leaves total,
  1 + 10 + 2 = 13.
