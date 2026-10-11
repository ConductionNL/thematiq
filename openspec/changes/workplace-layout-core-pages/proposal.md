---
kind: code
---

## Why

The light workplace layout painted the top bar and drew a login watermark, and left the rest of
Nextcloud's own pages as the NL Design sheets draw them for a coloured header: a login card with a
40 by 32 pixel logo stamp inside it, a dark translucent search bar with a bold label, a wide
transparent app grid button, a 50px bar. The approved Zuiddrecht boards (NcLogin, DqKop) draw a
light workplace: the logo above a 420px card with a hairline and the container radius, a 68px bar
with a 40px grid square and a 44px search pill on the workspace colour. Measured side by side on
Nextcloud 34 on 6 October 2026, those are the differences the stylesheet can close; the rest is
Nextcloud's own markup (the headline text, the floating field labels, the "remember me" box, the
device login link, the current-app entry in the bar) and stays.

One defect came out of the same measurement: on a guest page (a 403, a 404) the "Back to …" link
is dressed as a primary button and the NL Design link rule painted its label link-blue on the
button's blue, so the label vanished.

## What Changes

- **`css/workplace-layout.css` draws the login page and the guest pages** while the layout is
  `light`: Nextcloud's own guest logo above the card, 56px high, with the set's logo; the in-card
  stamp retired; the card 420px wide with a hairline, the container radius, the cards' shadow colour
  and 32px of padding; a 24px title; 44px controls with a 1px edge; the two text actions under the
  form at 14px, regular and underlined; the footer line muted with no plate; the guest action
  button's label in the login button's label colour.
- **The same sheet draws the top bar**: 68px high through `--header-height`, the app grid button a
  40px square on the workspace colour, the search field a 44px pill with a hairline and a muted
  regular label, and the dashboard's panel row transparent.
- No colour literal is written; every colour is the scheme's or a token's, so the sheet still
  suits any set an administrator turns it on for, in the light and in the dark.

## Out of scope

Everything the boards draw that Nextcloud's markup cannot: a subtitle under the login title, labels
above the fields, an SSO button and an "or" divider without an SSO app, a name and role next to the
avatar, a top bar without a logo. The theme picker's own markup (previews, a colour list, an apply
button) belongs to the admin panel, not to this sheet.
