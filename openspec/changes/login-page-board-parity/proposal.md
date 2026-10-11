---
kind: code
---

## Why

Ruben asked on 11 October 2026 for the login page on :8080 to match the NcLogin board and its
four variants (esdoornveen, vaartveld, wilgenboom, warmtepompacademie). The card, the logo, the
stripe and the watermark already matched. The words and the order did not. Nextcloud says
"Log in to Gemeente Zuiddrecht" with no subtitle, puts an SSO button under the form, draws the
password button as the primary action and closes with the name and the slogan joined by a dash.
The board says "Inloggen bij de werkplek", names who the page is for, puts the SSO button first
with an "of" divider, draws "Inloggen" outlined, and closes with a line of its own.

## What Nextcloud lets an app change on the login page

| Board element | Mechanism | Owner |
|---|---|---|
| Logo above the card, card, stripe, watermark | Stylesheet on the login page (already shipped) | thematiq |
| Title "Inloggen bij de werkplek" | Not a Nextcloud setting (core writes "Log in to {name}"); a script writes the set's copy | thematiq |
| Subtitle | Not a Nextcloud setting; the script adds it | thematiq |
| SSO button | `IAlternativeLogin`, registered by a login app (user_oidc) when a provider exists | the login app plus instance configuration |
| SSO label | The login app's name for the provider; the set's copy relabels a single button | thematiq |
| SSO first, "of" divider | The script moves the alternative logins above the form in the DOM, so focus order matches | thematiq |
| Outlined "Inloggen" | Stylesheet, only while an SSO button is there | thematiq |
| Field label "Gebruikersnaam of e-mail" | Core's Dutch reads "Gebruikersnaam of email"; the set's copy relabels it | thematiq |
| "Onthoud mij", "Inloggen met een apparaat" | System config `remember_login_cookie_lifetime` and `auth.webauthn.enabled` | instance administrator |
| Footer line | The script replaces the name and slogan, and keeps the legal links | thematiq |
| Footer hint with a portal link | The link is per instance, so only the administrator's override carries it | instance administrator |

## What changes

- **A `login` block per token set** in token-sets.json: `title`, `subtitle`, `sso_label`,
  `username_label`, `divider`, `footer`, `footer_hint`, `footer_link_label`, `footer_link_url`.
  Each value is one string or a map from language to string. Zuiddrecht and the four school and
  academy sets carry the copy of their boards in Dutch and English.
- **An administrator override** in the app config key `login_copy`, the same JSON shape, key by key.
- **`LoginCopyService`** resolves the copy for the page's set and language and hands it to
  **`js/login-copy.js`** as initial state, on the login page of the light workplace only.
- **`css/workplace-layout.css`** lays the card out in the board's order when the script has run.
  Zuiddrecht names the outlined button's edge in a new token, `--nldesign-login-secondary-border-color`
  (#C4C7CB, the board's field grey); every other set falls back to the dark border colour.

**Out of scope:** installing or configuring a login app, and the two system settings above. A
Nextcloud core change is not needed for any element.

## Capabilities

### Modified capabilities
- `workplace-layout`: the light workplace draws the login card of the board, with copy per set.

## Impact
- `lib/Service/LoginCopyService.php` (new), `lib/Listener/ThemeInjectionListener.php`, `lib/Service/CssInjectionService.php` (`isContextThemed()` public),
  `js/login-copy.js` (new), `css/workplace-layout.css`, `css/tokens/zuiddrecht.css` and its dark variant,
  `token-sets.json`, `l10n/*` ("or"), `.gitignore` (the new script is source).
- Sets on the default layout: unchanged. The script loads only with the light layout.
