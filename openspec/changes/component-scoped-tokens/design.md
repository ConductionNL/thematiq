# Design: component-scoped tokens

## Decision 1 — Re-scope the variable, do not repaint the component

The house style in `css/systems/nldesign/theme.css` is an `!important` element rule per
component, and buttons, links, headings and form fields are already done that way. Extending
it was the obvious move and was rejected.

Roughly twenty of the 34 playground components have no thematiq rule at all today — the app
navigation, the sidebar, dialogs, the progress bar, checkboxes, breadcrumbs, list rows,
counter bubbles. Painting each of them would mean writing selectors against Nextcloud
internals, with `!important`, for components whose markup Nextcloud is free to change. The
repo already records what that costs: the class-name drift guard in
`playgroundInventory.spec.js` exists because `.menutoggle`, `.unified-search__button` and
`.app-content-detail` were all names Nextcloud does not emit.

Redeclaring the variable inside the component's subtree needs no selector knowledge beyond
the component's root, and leaves the painting to the stylesheet that ships with the component:

```css
.app-navigation {
	--color-primary-element: var(
		--nldesign-component-navigation-active-background-color,
		var(--thematiq-global-color-primary-element)
	);
}
```

**No `!important` is needed, and that is not an oversight.** Custom properties cascade per
element. A value inherited from `:root` is only used by an element that has no declaration of
its own, so a declaration on `.app-navigation` wins inside that subtree regardless of the
importance of the `:root` one. This is also why `custom-overrides.css` writing
`:root { --color-primary-element: … !important }` does not leak past a component scope — which
is exactly the isolation the change is for.

## Decision 2 — Capture the globals under `--thematiq-global-*`

The fallback has to be the global, so that a component nobody has themed is indistinguishable
from stock. It cannot be written directly:

```css
/* INVALID — discarded, and takes the component with it */
.app-navigation {
	--color-primary-element: var(--X, var(--color-primary-element));
}
```

A custom property whose value depends on itself is invalid at computed-value time. This is the
same cycle `StockTokensService` documents for `--nldesign-color-primary: var(--color-primary)`
against `overrides.css`.

So `:root` copies each global first:

```css
:root {
	--thematiq-global-color-primary-element: var(--color-primary-element);
}
```

The copy is resolved at `:root`, where `--color-primary-element` holds its ordinary value, so
there is no cycle. The capture is deliberately **not** `!important`: `custom-overrides.css`
writes admin-set globals at `:root` with `!important` and loads later, and the capture has to
lose to it — an admin who moves the brand primary must move every component that has not been
given a value of its own.

## Decision 3 — The component tokens are declared in exactly one place

`css/component-scopes.css` declares no component token, only `var()` references to them. The
declarations live in `css/systems/nldesign/defaults.css` and nowhere else.

That is what makes the layer safe to load under every design system, including `none`,
`summer-breeze`, `high-contrast`, `lasuite` and `cunningham`. Under those, the component
tokens are simply undefined, every `var()` falls through to the captured global, and the page
is byte-identical to one without the layer. A design system that wants component-level control
opts in by declaring the names.

## Decision 4 — `primary_drives_components` defaults to OFF

Off is both the new behaviour and a no-op on upgrade, which is why it can be the default. With
no per-component value stored, every component token resolves through `defaults.css` to the
brand primary already, so an instance that has never opened the playground renders the same
either way. The setting only starts to matter once someone sets a component value — which is
the point at which the admin should be choosing.

On is implemented as a stylesheet rather than as a delete. `css/primary-lock.css` forces the
26 `primary`-flagged tokens back to the captured global with `!important`, and is emitted
**after** `custom-overrides.css` so it outranks a value stored there. Nothing is removed from
`custom-overrides.css`, so switching the setting off restores what the admin had. A delete
would have been simpler to implement and would have silently destroyed work.

## Decision 5 — The brand globals stay editable, and no chip owns them

`playgroundInventory.spec.js` asserted that every registry token is reachable from some chip,
on the reasoning that a token no component reads is one an admin can only find by scrolling.
That rule cannot survive a brand layer: the globals have no component to belong to, because
moving one is *meant* to move everything that has not opted out.

The alternative considered was a synthetic `Brand` chip owning the primary family, which would
have kept the guard passing unchanged. It was rejected because a chip is a drawing of a
component and the brand level is not one — and because the `primary_drives_components` control
belongs with the other admin toggles, not inside the instrument.

So the guard now exempts the brand globals by name, reading the list from the same mapping
file, and gains the inverse assertion: no chip may name a global. The coverage rule still has
teeth where it matters — all 123 component tokens must be reachable from a chip.

## Decision 6 — One table, read by both runtimes

`scripts/mapping/component-tokens.json` is read by `TokenRegistry.php` (what the editor may
write), by `scripts/generate-component-scopes.mjs` (what the CSS applies) and by
`playgroundInventory.spec.js` (what the chips may name). This follows
`scripts/mapping/nlds-to-nextcloud.json`, whose header states the same reason: PHP and JS both
load the file, so the two runtimes cannot drift.

The generator refuses to emit when two tokens of one component map onto the same global, since
that would produce two declarations of one property in one rule and the second would silently
win — one of the two chips' controls would do nothing.
