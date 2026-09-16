# Design: component playground

The decisions here are the ones the theming makeover left open, plus the ones the
presentation mock already answered in working code.

## 1. Vanilla and mock-shaped, not a Vue build

The first choice was a small Vue build for this page only, so the components are the
real `@nextcloud/vue` ones and cannot drift. It was not taken.

The deciding fact is that the approach already exists and works. `js/admin-mock.js` renders
a component stage with numbered callouts, filters the real token rows beside it, and
recolours the component live while a value is edited — behind `?mock=1`, in plain DOM calls,
with no bundler. What the playground needs is that mechanism applied to the full inventory rather
than to one button.

The cost of the alternative is not small. The repo has no build configuration at all: no
`vite.config`, no webpack, `src/` holds only `manifest.json`, and neither `vue` nor
`@nextcloud/vue` is installed. Taking the Vue route means adding three dependencies, a
bundler config, a build script, a dist artifact that must be rebuilt before every release,
and a reversal of the "no build for the admin panel" stance `project.md` states. That is a
change to how the app is shipped, made in order to render a page that a working mock already
renders without it.

The drift risk the Vue option removes is real and is handled instead by the class-name
contract in decision 4.

## 2. Inside the token editor, not a page of its own

The playground is built INTO the existing admin panel at `/settings/admin/theming`, in the
place and the shape `js/admin-mock.js` demonstrated: the token editor's four tabs move above
the preview and become the selector, a row of component chips goes under them, the preview
gains a third stage next to its app and login views, and choosing a component filters the
editor down to the tokens that component actually reads.

An earlier draft of this decision said "an admin-only page at `/apps/thematiq/playground`",
on the reasoning that only a full page can show the real header, navigation and sidebar. That
was the wrong trade and it is recorded here so it is not made again. The mock had already
answered the question in working code, and what it shows is not the live chrome but SPECIMENS
of it, drawn on the preview stage from the same variables — which works for the login card
and the login background too, neither of which a signed-in page can ever contain. Against
that, a separate page costs a route, a controller, a template, a second copy of the token
editor's state, and a second place for an admin to look for the same file.

The stage draws each component in its own states, with a numbered marker per state, and the
filtered token list repeats those numbers. That numbering is the whole mechanism: it is what
ties "this colour" to "this row" without the admin having to know a variable name first.

## 3. The editor's own rows, and therefore the editor's own saving

A component's token rows are the editor's rows, cloned. An edit made in a clone is written
back into the original input and re-dispatched there, so `admin.js`'s dirty tracking, its
reset buttons, its "customised" badge and its Save all see exactly what they would have seen
if the admin had typed in the full list.

This is the decision that removes the most code. There is no second store, no second save
path, no merge of two override maps, and no ordering problem between a save and a stylesheet
reload — the panel already solved all of that for the four-tab list, and a component view that
re-solved it would only be able to disagree with it.

The live recolour is scoped to `#nldesign-preview` rather than to the document: the stage
sits inside that element and inherits from it, so dragging a colour picker repaints the
specimen being judged and leaves the settings page around it alone. Applying the value to the
whole document would repaint the very panel the admin is working in, and — because
`theme.css` declares the token layer on `body` with `!important` — would need an inline
`!important` on `body` to take effect at all. Scoping to the preview needs neither.

## 4. The inventory is data, and a test holds it to the code

`js/playground/components.json` is the single source for what each chip shows. An entry names
the tab the chip appears under, the component's states, the tokens it reads with what each
one paints and which state it belongs to, the facts it depends on that have no token at all,
and the class names its specimen markup uses.

Four tests keep it honest:

- every token listed exists in `TokenRegistry`, or there is no editor row to clone;
- between them the components reach every token the editor can write, so the chips are a
  complete way into the same file the four-tab list edits;
- every component has stage markup and every piece of stage markup has a component;
- every class name listed appears in the stylesheets Thematiq ships.

The last is the drift guard the Vue option would have given for free. It fails when Nextcloud
renames a class out from under a copied fragment, which is the failure mode that matters: a
specimen that silently stops being styled looks like a broken theme rather than a stale copy.

## 5. Rows with no token, and why they are not "locked variables"

An earlier draft of this decision had the panel LOCK the `--color-primary-element*`,
`--color-primary-light*`, `*-rgb` and `--color-main-background*` families, on the reasoning
that Nextcloud derives them. That was wrong twice over: `TokenRegistry` lists most of them as
editable and the four-tab list has always edited them, and `css/systems/nldesign/overrides.css`
sets them explicitly — so an override does stick. Locking them would have made the component
view strictly less capable than the list it sits in, for a reason that is not true.

What the mock actually has, and what this change carries, is a row for something a
component's look depends on that has NO token: the 50 % opacity of a disabled button,
Nextcloud's clickable-area floor, a logo that is an asset rather than a value. Those rows show
the fact, no editor, and the converter's own reason code — `derived-by-nextcloud`,
`clickable-area-locked`, `logo-extracted` — so the panel and an import report explain the same
fact in the same words.

## 6. Export is the set, not the diff

The export button serialises the active set's resolved `--nldesign-*` values with the admin's
SAVED overrides folded in, as one flat `:root { }` block sorted by name. It is a complete set,
not a patch: the custom-set upload and `css/tokens/*.css` both expect a whole set, and the
vocabulary audit can only rate something complete if every required token is present.

Saved and not unsaved: a token set file is a thing an admin hands to another instance, and
exporting what is merely typed would produce a file no instance is wearing. An override reaches
the file through the `--nldesign-*` token that `overrides.css` says the variable reads, so the
mapping is the stylesheet's rather than a second copy of it, and an override that maps to no
token is reported rather than dropped.

The export therefore round-trips: exporting the active set with nothing overridden must produce
a file the audit rates exactly as it rates the set it came from. A vitest case pins that.

That map is many-to-one, and this is the direction where it costs something. Four Nextcloud
variables read `--nldesign-color-primary`, `TokenRegistry` makes more than one of them
editable, and the file has a single line to carry them — so two overrides can compete for one
token. Writing each in turn left whichever came last in the file and the other nowhere, with
nothing reported, which breaks the guarantee this decision is built on. The winner is chosen by
the same rule `StockTokensService::canonical()` applies to the same map in the other direction
(decision 8): the variable carrying the token's own name defines it, and where none does,
sorted order decides. Using one rule for both halves is not tidiness — a token that took its
stock value from `--color-primary` and its exported value from `--color-primary-element` would
make a round-trip disagree with itself. The losers are reported alongside the overrides no
token could carry, naming which override took the token, because "your set now has this colour
and not that one" is the thing an admin has to know before handing the file on.

## 7. What is translated in a specimen, and what is not

A specimen is a picture of a Nextcloud screen, and a picture has two kinds of words in it.
The line between them is worth stating, because the first reading of any Dutch string in this
file is "someone forgot `t()`".

**The drawn interface's own labels are translated.** "Log in", "Save", "Cancel", "Name",
"Size", "Modified", the empty state, the field errors, the switch's on and off — Nextcloud
translates every one of these in the real product, so a specimen that leaves them in one
language is not showing a German admin what their theme does to a German screen. The same goes
for everything the instrument SAYS about a specimen: the marker tooltips, the line under the
stage, the version switch. These all go through `t()` and live in `l10n/`.

**The sample content is not.** File names, people's names, dates, file sizes, the text of a
mock Woo record: `Jaarverslag 2025.pdf`, `Jan Bakker`, `2,4 MB`, `9 maart 2026`. These are not
labels, they are the material a label is wrapped around, and translating them means inventing
a German person and a German file. Where a sentence mixes the two — "Shared {name} with you" —
the sentence is translated and the sample name is a placeholder.

The practical test when adding a specimen: would Nextcloud itself ship this string in a
language file? If yes it goes through `t()`; if it is the content of a record, it does not.

## 8. The `nextcloud` set is resolved from the instance, not from a file

The playground is an instrument for judging what a token set does, and the first set anyone
judges against is `nextcloud` — the one that is supposed to look like the instance with no
theme applied. That set was a hand-copied snapshot in `css/tokens/nextcloud.css`, and a
snapshot of a moving target is wrong the moment the target moves. Measured on 34.0.4 against
the theme the server itself serves: of the 29 values that map onto a live Nextcloud variable,
12 matched and 17 did not. Several were not merely stale but inverted in role — Nextcloud
turned `--color-error` from a saturated fill into a pale background with its own `-text`
token, so the set that exists to look like stock was painting NC29-era fills onto NC34. The
file's own header records the previous round of the same bug: it claimed `#0082c9` after NC29
had moved to `#00679e`.

An instrument drawn against values that are wrong by a third is worse than no instrument, so
`StockTokensService` resolves the set from `DefaultTheme::getCSSVariables()` — the same
computation that produces `/apps/theming/theme/default.css` — and `css/tokens/nextcloud.css`
stays as the fallback. Three things about that are load-bearing, and all three are requirements
in the `nextcloud-variable-mapping` delta:

- **A stylesheet cannot do this.** Writing `--nldesign-color-primary: var(--color-primary)`
  closes a loop with `overrides.css`, which already says the opposite, and CSS discards a
  custom property that depends on itself. The values have to be resolved before they reach the
  page, and only literals may leave — a gradient or a `color-mix()` is dropped rather than
  frozen into a token that would resolve differently in the browser.
- **The inverse mapping is many-to-one, and the choice is visible.** Four variables read
  `--nldesign-color-primary`, and their stock values differ: `--color-primary` is `#00679e`
  while `--color-primary-light-text` is `#00293f`. Taking whichever came last gave the token a
  text colour, which paints the header a shade nobody asked for. The variable carrying the
  token's own name defines it; where none does, sorted order decides, so the output does not
  depend on the order declarations appear in `overrides.css`.
- **Every failure path degrades to the file.** The theming app is another app and may be
  absent; `\OCP\Server::get()` on its class is wrapped, the class is named as a string rather
  than imported so this file stays parseable without it, and a failure is logged because the
  fallback is otherwise silent. A stale stock theme is a cosmetic defect; no stock theme at all
  is a blank page.

This is the instance's stock theme rather than Nextcloud's factory one. An admin who has set a
primary colour in core theming is wearing that colour, so that colour is what the set reports —
which is the right answer for an instrument whose question is "what does my theme change".

**It resolves the INSTANCE's colours, not the current user's.** `DefaultTheme` stores two:
`defaultPrimaryColor` from `getDefaultColorPrimary()`, which reads the admin's app config only,
and `primaryColor` from `getColorPrimary()`, which returns the signed-in user's own
`primary_color` whenever they have set one — and user theming is on by default. All twelve
`--color-primary*` variables `overrides.css` maps are computed from the second. Taking it
verbatim made this service's output per-user, which a token set is not: it is injected
instance-wide, it is served to the anonymous login page, and it is cached under a key with no
user in it, so the first user to warm that cache after choosing a personal colour would have
dressed everyone else in it. The admin colour is substituted before the variables are
generated, so core still does every derivation and the answer is the same for every request.
The other user-dependent variables are the four `generateUserBackgroundVariables()` emits, and
`overrides.css` maps none of them.

**And it is cached across requests, because of what `nextcloud` is.** It is the DEFAULT set:
an instance that never opened this app is wearing it, and the layer list is built on every
`BeforeTemplateRenderedEvent` and on the login page. Resolving meant a stylesheet read and
regex scan plus the theming app's whole variable computation, per request, forever — where
before this change the same layer was a `<link>` to a static file and cost no PHP at all.

So the resolved block is kept in a distributed cache under a key of the installed Nextcloud
version and the theming app's cachebuster. That pair is exactly what can change the answer —
the variables are the release's own code, and core bumps the cachebuster whenever an admin
changes anything in its theming settings — so the cache invalidates itself on an upgrade or a
theming edit and needs nothing to clear it. Only a success is stored: a failure is the theming
app being absent or throwing, neither of which moves the key when it is fixed, so a cached
failure would outlive its cause. `TokenSetPreviewService::getTokenSources()` memoises its
`overrides.css` parse for the same reason at request scope.

What remains is that the token layer for this one set is an inline `<style>` rather than a
cached static asset, so it is re-sent in every HTML response. That is inherent to resolving at
all — the values are per-instance, so there is no static file to link — and it is the price of
the set being right rather than three years stale.

## 9. The login specimens are painted by a vendored, rewritten copy of core's guest.css

The settings page already loads the shipped stylesheets for most of what the specimens stand
for — the NcButton, NcInputField and NcCheckboxRadioSwitch chunks are all imported by
`dist/theming-settings-admin.css` — so a specimen carrying the right class names and the right
Vue scope attribute is painted by the component's own rules (decision 1).

`core/css/guest.css` is the exception: Nextcloud emits it on the login page and nowhere else.
It is also the file that owns everything an admin looks at on a login specimen — `.guest-box`
(the translucent surface, the blur, `--border-radius-container`, the shadow), the logo's
175x130 box, the `h2`, the bold links, the footer that carries the slogan. Hand-drawing that
card was the alternative, and a hand-drawn card is right on the day it is written and quietly
wrong after the next release.

So the file is vendored and re-emitted under one scope, and that is a contract rather than a
copy:

- **Source.** `scripts/sources/nextcloud-guest.css` is `core/css/guest.css` from
  `nextcloud/server v34.0.0`, vendored verbatim. `scripts/generate-guest-css.mjs` names the
  release in `SOURCE_RELEASE` and stamps it into the generated header.
- **Output.** `css/playground-guest.css` is generated, never hand-edited. `npm run
  generate:guest-css` writes it; `npm run test:guest-css` regenerates to a temporary file and
  diffs it against the committed copy, so the two cannot drift apart unnoticed. Because the
  check compares generator OUTPUT against the committed file, a fix belongs in the generator
  and is then regenerated — editing the CSS directly is exactly what the check exists to catch.
- **Why `:where()`.** The scope is `:where(#nldesign-preview .nldesign-pg-guestpage)`, which
  contributes zero specificity. A plain prefix would not just place these rules, it would
  promote them: upstream `button { background-color: var(--color-main-background) }` is (0,0,1)
  and loses to NcButton's `.button-vue[data-v-…]` at (0,2,0), which is why the log-in button is
  not a white box. Prefixed with an id and a class it would be (1,2,0) and would start winning,
  inverting the cascade the whole file was written inside.
- **What is rewritten.** Five things, each argued in the generator's header: `html` is dropped,
  `body` and `#body-login` become the scope root minus the viewport-only declarations, `#header`
  becomes `.header-guest` (the settings page already has an element with the id `header`), and
  `@keyframes` blocks are dropped as global names the page already carries. Relative `url()`
  references are re-expressed from `core/css/` to this app's `css/`.
- **Bumping the snapshot.** Replace `scripts/sources/nextcloud-guest.css` with the file from the
  new release, update `SOURCE_RELEASE`, run `npm run generate:guest-css`, and read the diff of
  the generated file — a rewrite that stopped matching shows up there rather than on the stage.

On licensing there is nothing to reconcile: the vendored file keeps its upstream
`SPDX-License-Identifier: AGPL-3.0-or-later` and both copyright lines, the generated file
reproduces them, and neither is relicensed. AGPL-3.0 is on the EUPL-1.2 compatibility list and
the file is aggregated rather than merged into this app's own EUPL-1.2 sources.

## 10. What this change does not do

It does not build the OpenWOO reference set. The playground is the tool; authoring the values
is a separate act of design work that follows it, and the acceptance criterion below
(export an `openwoo.css` the vocabulary audit rates complete) is the handover between the two.

It does not replace the settings page's four-tab token editor. "Full view" is the first chip
of every tab and gives the complete list back, unchanged, for admins who already know which
variable they want.
