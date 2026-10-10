# Design: denhaag-component-tokens

Read at thematiq `development` `df5321f6` and portaliq `development` `b150def5` on
2026-10-02. Package CSS read from the npm tarballs of `@gemeente-denhaag/card` 5.1.4,
`process-steps` 4.3.3, `step-marker` 3.1.3, `action` 4.4.2, `file` 2.5.3, `contact-timeline`
4.1.3, `side-navigation` 4.2.4, `sidenav` 2.0.0 and `data-badge` 2.2.2 (all EUPL-1.2).

## D0. How the numbers were measured

Per file in `css/tokens/*.css`, a count of declarations matching `--utrecht-[a-z-]*:`,
`--nldesign-[a-z-]*:` and `--denhaag-[a-z-]*:`, then a join against the ids in
`token-sets.json`. Per package, the distinct `var(--denhaag-…)` names in `dist/index.css`.

| Package | Distinct properties read |
| --- | --- |
| card (case card) | 57 (55 `--denhaag-case-card-*`, plus `--denhaag-border-style-dashed`, `--denhaag-color-ocher-5`) |
| step-marker | 80 |
| process-steps | 30 |
| action | 47 (3 of them `--denhaag-file-focus-*`) |
| side-navigation | 28 |
| contact-timeline | 27 |
| file | 19 |
| sidenav (older side navigation) | 16 |
| data-badge | 0 `--denhaag-*`; 8 `--nl-data-badge-*` |

## D1. One mapping, in the bridge portaliq links

The mapping goes into `css/public-bridge.css`, not into each set. The bridge's own header
gives the reason: a per-set copy duplicates a mapping that is the same everywhere and makes
every new set a porting job. And it puts the Den Haag defaults on the one file portaliq will
link (the portaliq lane's change), so a portal needs no second link.

The section is generated from `scripts/mapping/denhaag-component-tokens.json` by
`scripts/generate-denhaag-bridge.mjs`, between the markers
`/* BEGIN GENERATED denhaag-component-tokens */` and `/* END GENERATED … */`. The hand-written
part of the bridge stays hand-written. `npm run test:denhaag-bridge` re-runs the generator
with `--check` and fails on any difference, the pattern of `npm run test:component-scopes`.

Alternative considered: a separate `css/denhaag-bridge.css`. Rejected: a second file is a
second link to forget, and portaliq already forgot the first.

## D2. Colour follows the set, geometry follows Den Haag

A colour property maps to the semantic layer, `--utrecht-*` first where the role layer has a
matching role, so a set with its own role layer keeps its look:

| Den Haag property (shortened) | Value |
| --- | --- |
| `case-card-background-color` | `var(--nldesign-color-background, #fff)`, the token the bridge already reads for the document surface |
| `case-card-border-color` | `var(--nldesign-color-border, #d0d0d0)` |
| `case-card-title-color` | `var(--utrecht-heading-3-color, var(--nldesign-color-text, #1b1b23))` |
| `case-card-subtitle-color`, `-context-color` | `var(--nldesign-color-text-muted, #545454)` |
| `case-card-action-color` / `-active-color` | `var(--nldesign-color-link)` / `var(--nldesign-color-link-hover)` |
| `case-card-background-hover-background-color` | `var(--nldesign-color-primary-light)` |
| `case-card-archived-*` | text muted on `--nldesign-color-background-dark` |
| `step-marker-current-background-color` / `-color` | `var(--nldesign-color-primary)` / `var(--nldesign-color-primary-text)` |
| `step-marker-checked-background-color` / `-color` | `var(--nldesign-color-success)` / `#fff` |
| `step-marker-not-checked-color` / `-border-color` | `var(--nldesign-color-text-muted)` / `var(--nldesign-color-border-dark)` |
| `step-marker-error-*`, `-warning-*` | error and warning on white text |
| `process-steps-step-heading-current-color` | `var(--nldesign-color-text)` |
| `process-steps-step-heading-not-checked-color`, `-meta-color` | `var(--nldesign-color-text-muted)` |
| `action-background-color`, `-border-color`, `-color` | background, border, text |
| `action-date-color` / `-date-warning-color` | text muted / `var(--nldesign-color-warning)` |
| `action-indicator-background-color` | `var(--nldesign-color-primary)` |
| `action-single-hover-background-color` | `var(--nldesign-color-background-hover)` |
| `file-left-background-color` | `var(--nldesign-color-primary-light)` |
| `file-link-color` / `file-hover-color` | link / link hover |
| `file-focus-outline-color`, `focus-border` | `var(--nldesign-color-focus, #0b6ba8)` (no set declares a `--utrecht-focus-*` role) |
| `side-navigation-link-color` / `-active-color` / `-hover-color` | text / primary / link hover (same for `sidenav-*`) |
| `contact-timeline-step-meta-marker-color` | `var(--nldesign-color-text-muted)` |
| `nl-data-badge-{success,warning,error}-color` | the status colour |
| `nl-data-badge-{success,warning,error}-background-color` | `rgba(var(--nldesign-color-{status}-rgb), 0.12)` |
| `nl-data-badge-neutral-*` | text on `--nldesign-color-background-dark` |

The full list is the mapping file; this table is its shape. Every value ends in a literal
fallback, because the bridge's rule is that a missing token degrades to a default, never to
`unset`.

Geometry (gaps, paddings, radii, sizes, font sizes, line heights, flex directions) is copied
from `@gemeente-denhaag/design-tokens-components` at a pinned version, with the version and
the SHA-256 of the mapping file in the section's header. Radii read
`--nldesign-border-radius` where the property is a radius, so a set's own corner style
carries over.

## D3. The guardrail

`ShippedTokenSetAuditService` resolves each set over `css/systems/nldesign/defaults.css`
today. For the new pairs it also resolves the generated bridge section under the set, the
order a portal uses. It checks these pairs with `ContrastService`, at 4.5:1:

1. step marker current: `-current-color` on `-current-background-color`
2. step marker checked: `-checked-color` on `-checked-background-color`
3. step marker not checked: `-not-checked-color` on the page background
4. case card title and subtitle on `-background-color`
5. action date warning on `-action-background-color`
6. each badge variant's colour on its rendered fill (translucent fills are measured as they
   render, by the existing requirement)
7. side navigation link and active link on the page background
8. file link on the page background

A failing pair names the set, the property and the ratio. A pair it cannot resolve is
`unevaluated` and fails. The report gains one column group per component. The example school
sets prove the need: their brand files already override three role-layer step marker values
that sat at 1.3:1.

A set that fails gets a set-level override in its own file, as the school sets do, rather
than a change to the shared mapping. The mapping is right for most sets; the exception lives
with the set that needs it.

## D4. What a set may still do

A set that declares a `--denhaag-*` or `--nl-data-badge-*` property keeps it: the set loads
after the bridge. The audit judges the set's own value, not the bridge's. Uploaded custom sets
may carry such properties once `nlds-theme-converter` widens the validator.

## Build notes (2026-10-02)

- **37 properties the components read without a fallback are not in
  design-tokens-components 5.1.0.** 12 are older `sidenav-*` names; they read the newer
  `side-navigation-*` value. 13 are geometry, chosen from Den Haag's own common scale
  (`design-tokens-common` 4.0.0) and named with their source in the mapping's `explicit`
  block. The rest are colours and are mapped like every other colour.
- **The generator refuses a Den Haag colour the mapping does not name.** The first run found
  one (`--denhaag-case-card-list-background-hover-background-color`).
- **The portal page is white, not the login colour.** `resolveDeclarations()` falls back to
  `theming.background_color`, which is Nextcloud's login background (vng: `#0277BD`). A portal
  paints its page from `--nldesign-color-background` with the bridge's white fallback, so the
  Den Haag pairs are measured on that.
- **The Den Haag section sits inside the bridge's one `:root` block**, between markers, because
  a second `:root` is a duplicate selector to stylelint.

## Risks

- **Geometry differs from today's three sets.** `rotterdam`, `vng` and the school sets declare
  some geometry of their own; theirs wins, so nothing they show moves.
- **A Den Haag package update renames a property.** The mapping is pinned to package versions,
  and a vitest compares the pinned CSS's property names with the mapping, so an update shows
  the drift as a failing test rather than an unstyled component.
