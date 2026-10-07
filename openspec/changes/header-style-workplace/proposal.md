---
kind: code
---

## Why

Ruben compared the :8080 workplace with the boards on 7 October 2026. The top bar did not match
DqKop. It showed the wordmark and the app name, a stripe under the bar, and only an avatar at the
end. The board draws no logo and no app name, a thin grey line under the bar, and the bell, a
divider and the person: initials, name, role and a chevron. Nextcloud's account menu cannot show a
name or a role.

## What changes

- **A seventh layout option, `header_style`** (`default` or `workplace`), stored, saved and audited
  with the others, shown on the admin page, carried in the configuration bundle, and following the
  set's `layout` block while the administrator leaves it empty. A set that names none keeps
  Nextcloud's bar.
- **`css/header-workplace.css`**, loaded only while the layout is light and the style is
  `workplace`: no logo, no app name, the search after the grid button, the outline bell with the
  accent dot, the divider, no contacts menu, and the person's label.
- **`HeaderUserService` and `js/header-user.js`.** The page hands the signed-in person's display
  name and profile role (`IAccountManager::PROPERTY_ROLE`) to the script as initial state. The
  script puts an `aria-hidden` label in the account menu and stretches the menu's own button over
  it, so a click opens the real menu and the button keeps its own name. Without the script the bar
  keeps the avatar.
- **Zuiddrecht** names `header_style: "workplace"` and `brand_stripe_placement: "login"`: the
  stripe stays on the login card and leaves the bar.

**Out of scope:** the search field's text is Nextcloud's ("Search apps, files, tags, messages")
and stays; the board's placeholder is a mock. A person's photo avatar gives way to initials in
this style, as the board draws.

## Capabilities

### Modified capabilities
- `workplace-layout`: the header style joins the layout options.

## Impact
- `lib/Service/LayoutOptionValues.php`, `lib/Service/LayoutOptionsService.php`,
  `lib/Service/HeaderUserService.php` (new), `lib/Service/CssInjectionService.php`,
  `lib/Controller/LayoutController.php`, `lib/Settings/Admin.php`, `templates/settings/admin.php`,
  `js/admin.js`, `js/header-user.js` (new), `css/header-workplace.css` (new), `token-sets.json`,
  `l10n/*`, `docs/features/toggles.md`.
- Every other set: unchanged, measured by `LayoutOptionsServiceTest` walking every shipped set and
  by the injection test's controls.
