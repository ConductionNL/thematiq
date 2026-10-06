---
sidebar_position: 5
---

# Optional Toggles

Thematiq provides optional CSS-based toggles and two layout options that adjust Nextcloud's interface beyond color theming.

## Hide Login Slogan

**Setting:** `thematiq:hide_slogan`

When enabled, hides the tagline text ("a safe home for all your data") shown below the Nextcloud logo on the login page.

**Why use it:**
- Government organizations often have their own branding guidelines that don't include Nextcloud's default slogan
- Creates a cleaner, more professional login page
- Reduces visual clutter for public-facing instances

**How it works:** When enabled, Thematiq loads an additional CSS file (`css/hide-slogan.css`) that sets `display: none` on the slogan element.

## Show Menu Labels

**Setting:** `thematiq:show_menu_labels`

When enabled, displays text labels next to the icons in Nextcloud's left sidebar navigation.

**Why use it:**
- Improves accessibility — icon-only navigation can be confusing for users unfamiliar with Nextcloud
- Meets Dutch government accessibility guidelines (WCAG AA) which recommend text alternatives for icons
- Especially helpful for organizations onboarding many new users

**How it works:** When enabled, Thematiq loads `css/show-menu-labels.css` which overrides Nextcloud's default icon-only sidebar layout to include text labels.

## Enabling via Command Line

Both toggles can be set via the Nextcloud `occ` command:

```bash
# Hide the login slogan
php occ config:app:set thematiq hide_slogan --value=1

# Show menu labels in the sidebar
php occ config:app:set thematiq show_menu_labels --value=1

# Disable (set back to 0)
php occ config:app:set thematiq hide_slogan --value=0
```

## Workplace layout

**Setting:** `thematiq:workplace_layout` (`default`, `light`, or not set)

The light workplace layout gives the top bar the main background and its text colour: white in the light scheme, dark in the dark one. Use it when a theme paints the top bar in a brand colour and you want a calm, light bar instead.

The setting has three states in the admin panel:

- **Follow the theme.** Nothing is stored. The layout takes the default of the active token set. Most sets have none, so they keep the top bar they always had. Gemeente Zuiddrecht turns the light layout on.
- **Default.** The top bar the theme paints.
- **Light top bar.** The light layout, whatever the theme says.

Your choice always wins over the theme. A header colour you set in the token editor still wins over the layout.

One limit: the layout cannot redraw a logo. A theme whose logo is white, drawn for a coloured bar, needs a logo that reads on a light bar first.

**How it works:** while the layout is `light`, Thematiq loads `css/workplace-layout.css`. It redeclares the variables the header rules already read, so it writes no colour of its own.

**The login page and the top bar.** With the light layout, Nextcloud's own pages follow the workplace boards: the login page shows the theme's logo above the card, 56px high, and the card is a workplace card (420px wide, a hairline, the container radius, 32px of padding, a 24px title, 44px controls). The top bar is 68px high, the app grid button a 40px square on the workspace colour, the search field a 44px pill with a muted label. On a guest page (a 403, a 404) the "Back to …" button keeps a readable label. What Nextcloud's markup draws stays as it is: the title text, the labels inside the fields, "Remember me", the device login link and the current app in the bar.

**Login watermark.** With the light layout, a theme can show its emblem on the login page: large, faint, in the bottom corner behind the login card. The theme names the image in `--nldesign-login-watermark-image` and may set `--nldesign-login-watermark-opacity` (0.07 when unset). A shipped set gets this by adding `img/logos/<set>-emblem-grey.svg`. A theme that names no image shows none.

## Brand stripe

**Setting:** `thematiq:brand_stripe` (`1`, `0`, or not set)

A stripe of three colours along the bottom edge of the top bar. It is off for every theme that does not turn it on. Gemeente Zuiddrecht does: red, blue and red in the ratio 6 : 3 : 1, 5px high.

The setting has the same three states: **Follow the theme**, **On** and **Off**.

A theme sets the stripe with seven tokens:

| Token | What it sets |
|---|---|
| `--nldesign-brand-stripe-color-1`, `-2`, `-3` | The three colours, from start to end |
| `--nldesign-brand-stripe-ratio-1`, `-2`, `-3` | Their shares, as plain numbers such as `6`, `3` and `1` |
| `--nldesign-brand-stripe-height` | The height, such as `5px` |

A theme without these tokens gets three equal bands in its primary colours, 4px high.

**How it works:** while the stripe is on, Thematiq loads `css/brand-stripe.css`. The stripe sits inside the bar, so no app moves. It takes no clicks, so the controls in the bar keep working. On the login page the stripe runs along the top edge of the login card.

```bash
# Light top bar and brand stripe, whatever the theme says
php occ config:app:set thematiq workplace_layout --value=light
php occ config:app:set thematiq brand_stripe --value=1

# Follow the theme again
php occ config:app:delete thematiq workplace_layout
php occ config:app:delete thematiq brand_stripe
```

### Layout defaults in a token set

A shipped token set can carry defaults for both settings in `token-sets.json`:

```json
"layout": {
	"workplace_layout": "light",
	"brand_stripe": true
}
```

They apply only while the administrator has stored no choice.

## Theming per App

**Setting:** `thematiq:disabled_apps` (JSON array of app ids, default `[]`)

By default the Thematiq theme applies to every Nextcloud app. The **Theming per app** section in the admin panel lists each enabled app with a checkbox (checked = themed). Unchecking an app and saving adds it to an exclusion list: that app's pages then render with **stock Nextcloud styling**.

**Why use it:**
- Roll a municipal huisstijl out incrementally instead of all-or-nothing
- Quarantine a complex third-party app (or one mid-migration) that breaks or looks wrong under the theme, without losing theming everywhere else

**How it works:** On every page render, Thematiq resolves the app id from the request path (`/apps/{appid}`) and, if that app is excluded, skips **all** style injection for the request — design-system stylesheets, the token set, `custom-overrides.css`, and both toggles above.

**Trade-off (by design):** suppression is request-scoped, so on an excluded app's pages the global header and navigation also render unthemed — that app's pages are fully stock Nextcloud. The login page, settings pages, and share links are never affected by the exclusion list and always stay themed. The ids `thematiq`, `settings`, and `theming` can never be excluded.

```bash
# Exclude the Calendar app from theming
php occ config:app:set thematiq disabled_apps --value='["calendar"]'

# Back to theming every app
php occ config:app:set thematiq disabled_apps --value='[]'
```
