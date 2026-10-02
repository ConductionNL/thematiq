## Context

Colour parity for the `lasuite` design system already shipped (violet brand-override, full
bridge coverage, generated Cunningham token base — see the `lasuite-parity` and `lasuite-stack`
specs). What remains is **layout**: five geometry mismatches measured live at 1280x720 between the
reference apps (La Suite Docs on localhost:3000, La Suite Messages on localhost:8900) and the
`lasuite`-themed Nextcloud (localhost:8080). All five live in
`css/systems/lasuite/element-overrides.css`, Layer 7 of the design-system-driven CSS architecture
(`css-architecture` spec) — the layer reserved for element-level structural styling, loaded last
before `tokens/lasuite` and `custom-overrides`.

The most consequential of the five is a **self-inflicted regression**: this app's own Layer 7 sets
`#header { position: relative !important; }` (element-overrides.css line 28). Stock Nextcloud
ships `#header` as `position: absolute` (out of document flow, `z-index: 2000`), which is exactly
why `#content-vue`'s `margin-top: 50px` (unrelated to this app, a Nextcloud core rule) lands flush
under the header with 0px gap in stock NC. Forcing `position: relative` returns the header to
normal flow, so the browser now also reserves its own 50px box height in the document — the two
50px values stack, producing the measured 54px of visible dead space (the extra 4px is border/
box-model rounding, not a second margin). Neither reference app nor stock Nextcloud has this gap.

The other four mismatches are additive styling gaps, not regressions: La Suite's white/no-shadow
navbar treatment, its grey-canvas/white-card figure-ground pattern for the main content area, and
its flush full-bleed shell + sidebar-with-shadow-instead-of-border treatment were simply never
modelled in Layer 7.

## Goals / Non-Goals

**Goals:**
- Match the five measured La Suite layout characteristics (navbar chrome, header/content gap,
  grey canvas, white content card, full-bleed shell + sidebar) in the `lasuite` design system's
  Layer 7 only.
- Preserve every existing colour-parity guarantee (`lasuite-parity`, `lasuite-stack`,
  `component-tokens`) — this change touches structural properties (position, margin,
  border-radius, box-shadow, background, padding), not colour tokens.
- Keep `nldesign`, `summer-breeze`, and `high-contrast` design systems byte-identical — no shared
  file outside `css/systems/lasuite/` is touched.
- Use only existing `--lasuite-*` tokens (`gray-000`, `gray-025`, `gray-100`,
  `--lasuite-border-radius`) — no new Nextcloud CSS custom properties.

**Non-Goals:**
- Row heights, nav-item font sizes, and pane counts (2-pane vs 3-pane) — these are Nextcloud
  component metrics and information-architecture decisions, not design-system theming, and are
  unaffected by any of the five changes below.
- Any change to `defaults.css` or `brand-override.css` — both are generated/sourced and
  drift-guarded (`test:lasuite-tokens`, `test:lasuite-override`); `defaults.css` is additionally
  shared with the `cunningham` design system and must stay on the blue base.
- Any new `--color-*` mapping in `bridge.css`. All five changes are expressible with tokens
  `bridge.css` and `element-overrides.css` already expose; `bridge.css` is touched only if
  implementation surfaces a genuine gap (not anticipated from the measurements taken).
- Re-litigating the `--color-background-plain` / `--color-main-background*` non-mapping
  (`REQ-CSS-007`) — this change does not touch it.

## Decisions

### D1 — Drop `position: relative !important` on `#header` rather than compensating the margin

Two ways to close the 54px gap were available: (a) remove the `position` override so `#header`
reverts to Nextcloud's own `position: absolute`, or (b) keep `position: relative` and instead
override `#content-vue`'s `margin-top` to `0`. (a) is chosen because it removes an override this
app added on top of Nextcloud's own layout model rather than adding a second override to fight the
first; it is the smaller, more reversible diff, and it makes the `lasuite` system's `#header`
positioning behave identically to every other design system in this app (none of which override
`position` on `#header`). The paired `overflow: visible !important` is kept for now and
re-verified once `position` is no longer overridden — it exists to let the (currently absent in
`lasuite`) lint/ribbon pseudo-elements hang below the header in the `nldesign` system; since
`lasuite` doesn't use a lint bar, this is a no-risk verification step, not a functional change.

### D2 — Reuse `--lasuite-color-gray-025` for the main canvas instead of introducing a new token

The measured La Suite `<main>` background (`#f8f8f9`) is byte-identical to the existing
`--lasuite-color-gray-025` alias as resolved through the deployed (brand-override) cascade —
confirmed by reading `css/systems/lasuite/brand-override.css`, which redeclares
`--lasuite--globals--colors--gray-025: #f8f8f9` (the raw `defaults.css` Cunningham-blue-base value
is the very close but distinct `#f7f8f8`, intentionally left alone since it belongs to the
unstyled `cunningham` sibling set). No new token is needed; `var(--lasuite-color-gray-025)` is the
correct reference for the `lasuite` bundle.

### D3 — Content card is a new rule scoped to the list/detail body, not a rename of the existing `#app-content-vue` rule

`#app-content-vue` / `.app-content` currently render `--lasuite-color-gray-000` (white) with
`border-radius: var(--lasuite-border-radius)` — this rule becomes the grey canvas (D2) instead.
The white "card" La Suite shows inset ~22px from the sidebar is Nextcloud's own inner content
container, not `#app-content-vue` itself (measurements show the card sits at x=322, i.e. sidebar
width 300 + a 22px inset — that inset is Nextcloud's existing `.app-content` internal padding,
unaffected by this change). The implementer must identify the correct inner selector (candidates:
`#content-vue .list-view`, `.list-view`, `.app-content-list`, or app-specific content containers)
by inspecting the live DOM under `#app-content-vue` on the dev instance, since nldesign ships no
Vue frontend and cannot rely on component source for this selector — this is called out as an
Open Question below rather than guessed.

### D4 — Sidebar depth switches from `border-right` to `box-shadow`, matching the measured technique

Layer 7 currently gives `#app-navigation` both a `border-right: 1px solid gray-100` and
`border-radius: var(--lasuite-border-radius)` plus a floating `margin-right: 16px`. La Suite Docs'
sidebar instead sits flush (`x=0`, `border-radius: 0`) and expresses depth purely via
`box-shadow: rgba(0,0,0,.05) 10px 0 10px 0` with a hairline `border-right: 1px #e2e2ea` (kept, not
removed — the measurement lists both a shadow and a 1px border-right, so this change adds the
shadow and zeroes the radius/margin without dropping the existing border-right). This is a direct
transcription of the measured values, not a new visual idea.

### D5 — Navbar padding and border are literal transcriptions, height is untouched

Nextcloud's 50px `#header` height is a platform given (multiple other design systems in this app
build on it) and is explicitly out of scope per the proposal. Only `background-color: #ffffff`,
`box-shadow: none`, `border-bottom` kept at `1px` but recoloured to transparent (matching the
measured "1px but transparent colour" rule rather than removing the declaration outright, so any
future accidental colour reintroduction is a one-line change), and `padding: 0 18px` are added/
adjusted.

## Risks / Trade-offs

- **[Risk]** Dropping `position: relative !important` could reveal that something else in the
  `lasuite` Layer 7 (or an app-specific override) implicitly depended on the header being in flow.
  → **Mitigation**: `overflow: visible !important` is explicitly re-verified after the change
  (D1); the e2e parity spec and a live Playwright check of the themed Files view + login page
  (per the `lasuite-stack` spec's Visual Parity Verification requirement) are run before the
  change is archived.
- **[Risk]** The exact selector for the "content list" white card (D3) is not yet confirmed against
  the live DOM — guessing wrong would either leave the grey canvas without a visible card or
  double-apply the white background to the wrong container. → **Mitigation**: called out as an
  Open Question; implementer inspects the live dev instance DOM before writing the rule, per D3.
- **[Risk]** `tests/e2e/spec-coverage/lasuite-parity.spec.ts` currently hard-asserts `#header`
  `borderStyle: 'solid'` and a visible `gray-100` border colour (lines ~127, ~149) — both
  assertions become false once the border-bottom colour goes transparent. → **Mitigation**: the
  spec is explicitly listed as needing an update in the proposal's Impact section and in tasks;
  this is a known, planned test change, not an incidental breakage.
- **[Trade-off]** Keeping `border-right: 1px solid #e2e2ea` on the sidebar in addition to the new
  box-shadow (D4) is a deliberate choice to match the measurement literally (both were observed)
  rather than picking one depth technique — slightly more CSS than a shadow-only approach, but
  avoids removing something that was actually measured on the reference app.

## Migration Plan

No migration is required — this is a CSS-only change to one design system's Layer 7 stylesheet,
loaded the same way it already is (`\OCP\Util::addStyle()` via `DesignSystemService`, per the
`css-architecture` spec). No IConfig keys, database rows, or admin-facing settings change. Rollback
is a plain revert of `css/systems/lasuite/element-overrides.css` (and `bridge.css` if touched) plus
the corresponding test-file reverts; there is no data to migrate back.

## Open Questions

- Which concrete selector, inside `#app-content-vue`, is the "content list" container that should
  receive the white card treatment from D3 (measured at La Suite Docs' `x=322`, `background:
  #ffffff`, `border-radius: 4px`, no shadow)? Resolve by inspecting the live DOM on the dev
  instance under the active `lasuite` token set before writing the rule; do not guess a class name
  from source alone since nldesign ships no Vue frontend to grep for the real component tree.
- Is the measured `overflow: visible !important` on `#header` still needed for `lasuite` once
  `position: relative` is removed (D1)? It exists in `nldesign` to let a lint/ribbon pseudo-element
  hang below the header, and `lasuite` has no lint bar — resolve by testing with and without it on
  the dev instance and keeping whichever passes visual/e2e checks with the least CSS.
