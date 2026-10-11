---
kind: code
---

## Why

Ruben, 11 October 2026: "we need a better design for this not found page". Nextcloud's 404 page in
the light workplace showed the logo, then a card with an empty white block above "Pagina niet
gevonden" (the search icon draws nothing there), one sentence and a button back to the start page.
The NietGevonden board draws an error code above a large title, a longer explanation, "Of ga
verder naar" with a short list of links, and a closing line.

The board is the public website's 404, with the site's header, menu and footer around it. A
Nextcloud guest page has none of those, so this change takes the board's content column into the
guest card. The site chrome around it belongs to the website (portaliq), not to this page.

## What changes

- **The empty block goes.** In the light workplace the 404 page's search icon is hidden.
- **A `not_found` block per token set** in token-sets.json: `eyebrow`, `title`, `text`,
  `links_heading`, `links` (a list of `{label, url}`) and `hint`. Texts follow the login copy's
  rules: one string or a map per language, and a missing language keeps Nextcloud's words.
  Zuiddrecht ships the board's copy with one link, to the home page.
- **An administrator override** in the app config key `not_found_copy`, key by key. Links to a
  portal or a contact page differ per instance, so they belong here.
- **`NotFoundCopyService` and `js/not-found.js`.** The listener runs it for Nextcloud's own `404`
  template on a themed context, with the light layout only. The script writes text only. With
  links, Nextcloud's "Back to …" button gives way to them.
- **`CopyValues`** holds the value rules the login copy and this copy share.
- **`css/workplace-layout.css`** lays the card out as the board's column: 760px, left-aligned,
  24px between the parts, a 44px title, 21px text, 19px links with a chevron.

## Capabilities

### Modified capabilities
- `workplace-layout`: the light workplace draws Nextcloud's not-found page as the board.

## Impact
- `lib/Service/NotFoundCopyService.php` (new), `lib/Service/CopyValues.php` (new),
  `lib/Service/LoginCopyService.php`, `lib/Listener/ThemeInjectionListener.php`,
  `js/not-found.js` (new), `css/workplace-layout.css`, `token-sets.json`, `.gitignore`.
- Sets on the default layout: unchanged.
