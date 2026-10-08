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

**The standard apps.** With the light layout, the dashboard's panels, the Files list, the settings sections and the thematiq panel are cards on the grey workspace: the container radius, a hairline and the cards' shadow colour, with 18px card titles and a quiet 14px header row on the Files list. The theme picker's token set select is 44px high.

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

## Where the brand stripe is drawn

**Setting:** `thematiq:brand_stripe_placement` (`header-and-login`, `header`, `login`, or not set)

Only while the brand stripe is on. **Top bar and login card** is how the stripe has always been drawn and what a theme that names nothing gets. **Top bar only** leaves the login card plain; **Login card only** leaves the top bar plain, the way the Zuiddrecht workplace boards draw it (the stripe marks the login screen, the bar carries a hairline).

**How it works:** the stripe keeps its one stylesheet; a placement other than both loads `css/brand-stripe-header-only.css` or `css/brand-stripe-login-only.css` after it, which takes the other copy off again.

## Navigation width

**Setting:** `thematiq:navigation_width` (a whole number of pixels from `200` to `480`, or not set)

The width of the app navigation. Left empty, the width follows the theme; a theme that names none keeps the width Nextcloud gives the panel. Gemeente Zuiddrecht names 264.

**How it works:** while a width resolves, Thematiq loads `css/navigation-width.css` and one inline `:root` variable, `--thematiq-navigation-width`, that the stylesheet reads: Nextcloud's `--navigation-width`, the thematiq global and the `--nldesign-nc-navigation-width` token all take it, and the panel itself is sized outright as well.

## Selected navigation entry

**Setting:** `thematiq:navigation_active_style` (`default`, `soft`, or not set)

**Default** is the entry as Nextcloud draws it. **Soft tint with a bold label** draws the entry you are on as a flat wash of the accent's light tint (`--nldesign-color-accent-light`) with the accent's dark text (`--nldesign-color-accent-text`) as a bold label, the way the Zuiddrecht workplace boards draw it: #A30000 on #FCEDEC, 7.22:1. A theme without an accent uses its primary tint and its primary colour, a pair every shipped set already reaches AA on.

**How it works:** while the style is `soft`, Thematiq loads `css/navigation-active-soft.css`. Every colour is a token, so the generated dark variant keeps up on its own.

## Login watermark

**Setting:** `thematiq:login_watermark` (`1`, `0`, or not set)

With the light top bar, a theme can draw its emblem large and faint on the login page (see the workplace layout above). **Off** hides it whatever the theme says; a theme that names no image shows none either way.

**How it works:** while the layout is light and the watermark is off, Thematiq loads `css/login-watermark-off.css` after the layout stylesheet, which takes the mark off again.

## Top bar style

**Setting:** `thematiq:header_style` (`default`, `workplace`, or not set)

**Default** is the top bar as Nextcloud draws it. **Workplace** draws the bar of the Zuiddrecht workplace boards (DqKop), and only with the light workplace layout. It drops the logo and the current app's name, since the app navigation carries the brand. The search field sits right beside the app grid button. At the end you see the bell, a thin divider and your own initials, name and role.

**Where the role comes from:** the Role field on your personal profile (Personal info, Role). Without a role you see your name only.

**How it works:** while the layout is light and the style is `workplace`, Thematiq loads `css/header-workplace.css` and the small script `js/header-user.js`. The page hands the script your display name and role as initial state. The script puts them in Nextcloud's account menu, so a click on them opens that menu. Without the script, or before it runs, the bar shows Nextcloud's own avatar.

```bash
# The stripe on the login card only, a 264px navigation, the soft entry, no watermark
php occ config:app:set thematiq brand_stripe_placement --value=login
php occ config:app:set thematiq navigation_width --value=264
php occ config:app:set thematiq navigation_active_style --value=soft
php occ config:app:set thematiq login_watermark --value=0
php occ config:app:set thematiq header_style --value=workplace

# Follow the theme again
php occ config:app:delete thematiq brand_stripe_placement
php occ config:app:delete thematiq navigation_width
php occ config:app:delete thematiq navigation_active_style
php occ config:app:delete thematiq login_watermark
php occ config:app:delete thematiq header_style
```

### Layout defaults in a token set

A shipped token set can carry defaults for every layout setting in `token-sets.json`:

```json
"layout": {
	"workplace_layout": "light",
	"brand_stripe": true,
	"brand_stripe_placement": "login",
	"navigation_width": 264,
	"navigation_active_style": "soft",
	"login_watermark": false,
	"header_style": "workplace"
}
```

They apply only while the administrator has stored no choice. A set that leaves one out gets the behaviour every set had before the setting existed: the stripe in both places, Nextcloud's navigation width, the default entry, the watermark drawn, Nextcloud's own top bar. Every layout choice travels in the configuration bundle as the administrator stored it, "follow the theme" included.

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
