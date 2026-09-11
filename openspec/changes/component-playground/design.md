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

## 2. A page inside the real shell, not a canvas

The playground does not draw a fake Nextcloud. It renders component markup into a normal
admin page and lets the real cascade paint it, so the header, the navigation, the sidebar
and the toasts around the content are the actual themed chrome, not a copy of it.

Two consequences follow. The header and navigation sections are annotations pointing at the
live chrome rather than re-renders of it, because the page cannot contain a second header.
The login card is the exception: `#body-login` never appears on an authenticated page, so
that one section carries its own copy of the login markup, and decision 4's contract covers
it like any other.

## 3. Inline `important` for editing, the overrides file for saving

Editing writes `document.documentElement.style.setProperty(name, value, 'important')`. That
is the only mechanism that beats `theme.css`'s `body … !important` declarations without
writing a stylesheet, and it repaints the real component in the same frame.

It is deliberately not how the set switcher works. The no-reload apply change established that switching sets
swaps the stylesheet run, because inline variables cannot reproduce
`token-overrides/<set>.css` rules, the logo `background-image` or the dark variant's scoped
rules — a page that is almost right is worse than a reload. Per-variable editing has none of
those problems: one variable, one value, no rules.

Saving is the inverse. `POST /settings/overrides` merges the edits into
`custom-overrides.css`, the client bumps that stylesheet's `?v=`, and only once the new
sheet has loaded are the inline values removed. Dropping them earlier would flash the old
value between the save returning and the sheet arriving. This is the same ordering the apply
dialog uses.

## 4. The inventory is data, and a test holds it to the code

`js/playground/components.json` is the single source for what each section shows. An entry
names the Nextcloud variables the component reads, what each one paints, the `--nldesign-*`
token it comes from, and the class names the section's markup uses.

Three tests keep it honest:

- every Nextcloud variable listed exists in `TokenRegistry`;
- every `--nldesign-*` name listed is declared in `css/systems/nldesign/defaults.css`;
- every class name listed appears in the stylesheets Thematiq ships for that component.

The third is the drift guard the Vue option would have given for free. It fails when
Nextcloud renames a class out from under a copied fragment, which is the failure mode that
matters: a section that silently stops being styled looks like a broken theme rather than a
stale copy.

## 5. Derived variables are shown locked, never edited

`--color-primary-element*`, `--color-primary-light*`, every `--color-*-rgb`, and
`--color-main-background*` are computed by Nextcloud from other values. The panel lists them
with their current computed value and a lock, carrying the conversion reason code where one
exists, and offers no editor. Offering one would write an override that Nextcloud
recomputes on the next render, which reads as the playground losing the edit.

## 6. Export is the set, not the diff

The export button serialises the active set's resolved `--nldesign-*` values merged with the
unsaved edits, as one flat `:root { }` block sorted by name. It is a complete set, not a
patch: the custom-set upload and `css/tokens/*.css` both expect a whole set, and the
vocabulary audit can only rate something complete if every required token is present.

The export therefore round-trips: exporting the active set with no edits must produce a file
the audit rates exactly as it rates the set it came from. A vitest case pins that.

## 7. What this change does not do

It does not build the OpenWOO reference set. The playground is the tool; authoring the values
is a separate act of design work that follows it, and the acceptance criterion below
(export an `openwoo.css` the vocabulary audit rates complete) is the handover between the two.

It does not touch the settings page's four-tab token editor. Both write the same file through
the same endpoint; the compact view stays for admins who know which variable they want.
