# NL Design — Test Guide

> **Agentic testing (experimental)**: This guide is used by automated browser testing agents. Results are approximate and should be verified manually for critical findings.

## App Access

- **Admin Settings**: `http://localhost:8080/settings/admin/theming` (NL Design section)
- **Login**: admin / admin
- **Note**: NL Design has NO user-facing app page. It is a theming system with admin settings only.

## What to Test

Read the feature documentation:

- **Docs**: [docs/](docs/) — tokens, icons, CSS architecture, compliance, etc.
- **Specs**: [openspec/specs/](openspec/specs/) — admin-settings, css-architecture, token-sets, theming-sync, hide-slogan, menu-labels

### Features (All Admin Settings)

| Feature | Spec | What to Test |
|---------|------|-------------|
| Token Set Selection | token-sets | Dropdown with 39 token sets — select different ones, verify preview updates |
| Theming Sync | theming-sync | After changing token set, sync dialog may appear — test "Update theming" and "Cancel" |
| Hide Slogan | hide-slogan | Checkbox — toggle and verify login page slogan hides/shows |
| Show Menu Labels | menu-labels | Checkbox — toggle and verify app sidebar shows text labels instead of icons |
| Live Preview | admin-settings | Preview box should update colors when token set changes |
| CSS Injection | css-architecture | After applying a theme, navigate around Nextcloud and verify styling is consistent |

### Testing Flow

1. Navigate to `/settings/admin/theming`
2. Find the "Thematiq" section
3. **Token set**: Change from current to "Amsterdam" → preview should update → note if sync dialog appears
4. **Token set**: Change to "Rotterdam" → preview should update with different color
5. **Hide slogan**: Toggle checkbox → navigate to login page (log out and check) → verify slogan hidden/shown
6. **Menu labels**: Toggle checkbox → reload page → verify sidebar labels appear/disappear
7. **Global styling**: After applying a token set, navigate to different Nextcloud pages and verify consistent theming (header color, buttons, fonts)

### Key Interactions

- **Dropdown change**: Select different token set → preview updates → POST to server
- **Theming sync dialog**: Modal with "Current" vs "Proposed" preview → "Update theming" or "Cancel"
- **Checkbox toggles**: Each saves immediately on change
- **Documentation link**: Top-right link should open the documentation of the selected design system

## What NOT to Test

This app has no ROADMAP.md — all features are implemented. Test everything.
