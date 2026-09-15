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

## 7. What this change does not do

It does not build the OpenWOO reference set. The playground is the tool; authoring the values
is a separate act of design work that follows it, and the acceptance criterion below
(export an `openwoo.css` the vocabulary audit rates complete) is the handover between the two.

It does not replace the settings page's four-tab token editor. "Full view" is the first chip
of every tab and gives the complete list back, unchanged, for admins who already know which
variable they want.
