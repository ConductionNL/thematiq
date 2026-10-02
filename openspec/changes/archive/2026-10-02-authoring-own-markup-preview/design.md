# Design: preview your own components with the house style

## Where it fits (development `b4e7568`)

- **The playground** is `js/playground.js`, loaded after `js/admin.js` by
  `templates/settings/admin.php:32-42`. It rebuilds the token editor into a selector, a chip row
  and a stage (`boot()` at `:454`). The stage is a `div` inside the preview container
  (`:690-693`), and a live edit is written onto that container with
  `state.preview.style.setProperty(name, value)` (`:1083`). So the stage inherits the edited values
  through the cascade. `readVar()` (`:590`) reads a variable off an element.
- **What the playground draws** is data: `js/playground/components.json`, published as
  `playgroundInventory` by `PlaygroundStateService::getInitialState()`
  (`lib/Service/PlaygroundStateService.php`), which `lib/Settings/Admin.php:342-344` passes to
  `provideInitialState()`. The same call publishes `playgroundTokens`, the set's resolved
  `--nldesign-*` values (`TokenSetPreviewService::getResolvedTokens()`), and `playgroundSet`.
- **The selection hash** is `#preview={tab}/{component}`, parsed by `parseHash()` (`:297-331`)
  with `/^#preview=([a-z]+)\/([a-z0-9-]+)$/`. A hash the inventory does not know is ignored
  (`openspec/changes/component-playground/specs/component-playground/spec.md`, "The Selection Is
  Addressable").
- **The page's Content Security Policy** is Nextcloud's default for a settings page:
  `default-src 'none'` (`lib/public/AppFramework/Http/EmptyContentSecurityPolicy.php:396` in the
  Nextcloud 35.0.1 checkout), no inline scripts (`ContentSecurityPolicy.php:27`), inline styles
  allowed (`:43`), images from `'self'`, `data:` and `blob:` (`:49-53`), fonts from `'self'` and
  `data:` (`:67-70`), and no `frame-src` at all, because `allowedFrameDomains` is empty (`:65`) and
  the directive is only written when it is not (`EmptyContentSecurityPolicy.php:467-471`).
- **Thematiq uses no frame today.** No `iframe`, `srcdoc` or CSP change exists in `js/`, `lib/`
  or `templates/`.

The open change `component-playground` owns the chip row, the stage, the cloned token rows and the
hash. This change adds one chip, one stage and one frame to it.

## Decision 1: a chip in every tab, a stage with two fields and a frame

Every tab's chip row ends with "Your component". Choosing it opens a stage with an HTML field, a CSS
field, a light and dark switch, and the preview frame. The token list beside the stage filters to
the tokens the builder's code reads (decision 5).

Rejected: a sixth selector entry. The selector is the editor's four tabs by the playground's
design, and a builder's component is not filed under one of them.

## Decision 2: a sandboxed srcdoc frame

The frame is `<iframe sandbox="allow-same-origin" srcdoc="...">`, and the attribute value is fixed
by a unit test.

- No `allow-scripts`: nothing in the frame runs, whatever the markup says.
- No `allow-forms`, `allow-popups`, `allow-modals` or `allow-top-navigation`: a pasted form cannot
  submit, a link cannot open a window or move the settings page.
- `allow-same-origin` is on for one reason: the parent can then set custom properties on the
  frame's root directly, so a token edit repaints the frame without rebuilding it, and the frame
  can load the house style fonts from the app. With scripts off, same origin gives the framed
  markup nothing it can use. The combination that escapes a sandbox is scripts plus same origin,
  and the test in task 2.2 fails if `allow-scripts` ever appears.

Rejected: `sandbox=""` with no tokens. The frame gets an opaque origin, so the parent cannot reach
it and must rebuild `srcdoc` on every keystroke of a colour picker, and the app's fonts become a
cross-origin load that fails.

Rejected: rendering the markup straight into the stage `div`. The settings page's own styles would
leak into the builder's component, and the component's CSS would leak into the settings page.

## Decision 3: the frame's own Content Security Policy

The `srcdoc` document starts with
`<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:">`.
A `srcdoc` document also inherits the settings page's policy, so both apply and the stricter wins.
There is no `script-src`, so a script is refused even if the sandbox attribute were ever changed.
No external image, font, stylesheet or connection can load, so pasted markup cannot call home.

**Checked (task 1.1, 2 Oct 2026).** A local page served with Nextcloud's settings page policy
(`default-src 'none'`, no `frame-src`) and this frame:

| Browser | Renders | `frame-src` violation | Parent reaches the frame | Pasted script | External image |
|---|---|---|---|---|---|
| Chromium 151 (headless) | yes | none | yes, custom properties apply | refused by the sandbox | refused by both policies |
| Firefox | not checked: no Firefox in the lane | | | | |
| Safari | not checked: no Safari in the lane | | | | |

Chromium confirms the assumption. Firefox and Safari are owed before release (recipe in the PR
body); the shadow root fallback below stays the plan if either blocks the frame.

**The open question.** Nextcloud's page policy has `default-src 'none'` and no `frame-src`. The
change assumes that browsers do not apply `frame-src` to an `about:srcdoc` frame, because no
request is made. Task 1.1 verifies that in Firefox, Chromium and Safari before any other work. If
a browser blocks the frame, the fallback is a shadow root on the stage `div`: the same sanitiser,
the page's own policy already refuses inline scripts and handlers (`ContentSecurityPolicy.php:27`),
styles stay inside the shadow root, and custom properties still inherit into it. The fallback
loses the second policy layer and keeps the other two.

Rejected: adding `frame-src` to the page policy through `AddContentSecurityPolicyEvent`. That event
widens the policy of every page on the instance, for one admin panel.

## Decision 4: markup is cleaned against an allowlist before it reaches the frame

`js/lib/markupSanitizer.js` parses the HTML with `DOMParser`, which never runs scripts, and walks
the tree:

- **Elements kept**: structural and text elements, lists, tables, headings, `a`, `img`, `button`,
  `input`, `select`, `option`, `textarea`, `label`, `fieldset`, `legend`, `details`, `summary`,
  `dialog`, `svg` with its drawing elements. Everything else is removed with its content:
  `script`, `style` inside the HTML (CSS has its own field), `iframe`, `object`, `embed`, `link`,
  `meta`, `base`, `form`, `template`, `noscript`.
- **Attributes kept**: `class`, `id`, `role`, `aria-*`, `data-*`, `style`, `title`, `alt`, `type`,
  `name`, `value`, `placeholder`, `disabled`, `checked`, `for`, `open`, `tabindex`, the SVG
  presentation attributes. Every `on*` attribute is removed.
- **URLs**: `href` is kept only as `#fragment`. `src` is kept only as `data:image/...`. `url()` in
  a `style` attribute or in the CSS field is kept only for `data:`.

It returns the cleaned HTML and a list of what it removed, which the stage shows under the fields
("Removed: 1 script, 2 event handlers"), so a builder is never left guessing.

The server applies the same size limits on save and stores the raw text. The text is cleaned again
every time it is rendered, so a saved component can never skip the cleaning.

Rejected: vendoring DOMPurify. The app has no bundler, a vendored copy has to be updated by hand,
and the sanitiser is the third lock, not the only one.

## Decision 5: the frame wears the house style, live

When the code changes, the stage scans the CSS field and every `style` attribute for `var(--name`
references. For each name it reads the value off the preview container with `readVar()`, the same
element the playground writes live edits onto, and sets it on the frame's root. Every token edit in
the playground triggers the same copy for the names the frame reads. So the frame shows exactly
what the playground's own stage shows, unsaved edits included.

The house style fonts are copied the same way: the `@font-face` rules of the page's `fonts.css`
go into the frame's first `<style>`, loading from `'self'`.

The token list beside the stage filters to the scanned names, like a shipped component filters to
its tokens. A name the editor can write gets its editor row. A name the editor cannot write is
listed with its current value and the note "Read-only here: this comes from the token set".

## Decision 6: light and dark

The switch sets the frame's `data-theme-dark` attribute and copies the dark values: the set's dark
`--nldesign-*` declarations, published as a new initial-state key `playgroundDarkTokens` read from
`css/tokens/dark/{set}.css`, and the editor's dark values once `authoring-token-value-types` has
landed. Nextcloud's own `--color-*` variables keep the values of the theme the administrator is
using, and the switch says so: "Nextcloud's own variables show your current theme."

## Decision 7: saving own components

`OwnComponentService` stores components in the app's data folder (`IAppData`, folder
`playground-components`), one JSON file per component: `{name, slug, html, css, updatedAt}`. At
most 20 components, at most 64 KB of HTML and CSS together per component. Admin-only routes:
`GET|POST /settings/playground/components`, `DELETE /settings/playground/components/{slug}`.

Saved names appear in the chip's menu. The hash `#preview={tab}/own-{slug}` reopens one. A slug that
no longer exists is ignored, as a stale shipped component is.

No audit entry: a saved component changes nothing any user sees. The theming audit covers
operations that change theming (`openspec/specs/theming-audit/spec.md`, "Complete Call-Site
Coverage"), and this is a builder's scratch space.

Rejected: appconfig storage. Appconfig is read on many requests, and 20 times 64 KB does not belong
there.

## Risks

- **The frame-src question** (decision 3). Answered first, with a fallback that keeps two locks.
- **Sanitiser gaps.** An allowlist can miss an attribute that loads something. The frame policy
  refuses any load outside `'self'` and `data:`, so a gap renders nothing instead of leaking.
- **Builders expect scripts.** Interactive components will not move. The stage says so above the
  frame: "Scripts do not run here. You see the styled states, not the behaviour."

## Out of scope

- A preview for users who are not administrators. Pasted markup from any user is a stored
  cross-site scripting risk this change does not take on.
- Adding own components to `js/playground/components.json` or to the inventory test.
- Running component JavaScript or framework components (Vue, web components).
