# Thematiq features

Use this page to find a Thematiq feature and to check which standards it supports. Thematiq gives Nextcloud and every app on it a Dutch government house style. You pick a token set, or build your own, and Thematiq maps it onto the CSS variables Nextcloud already uses.

Thematiq ships 51 token sets in `token-sets.json`. Of those, 38 are NL Design System house styles, among them Rijkshuisstijl and more than 30 municipalities. The rest are the stock Nextcloud base, a high-contrast set, three La Suite sets, Summer Breeze, three Conduction and supplier sets, and four example school sets.

## Standards

Every claim below rests on code or a test in this repository. Where the app only does part of what a standard asks, the table says which part.

### Forum Standaardisatie

| Standard | List status | What Thematiq does |
|---|---|---|
| [Digitoegankelijk (EN 301 549 met WCAG 2.1)](https://www.forumstandaardisatie.nl/open-standaarden/digitoegankelijk-en-301-549-met-wcag-21) | Verplicht (pas toe of leg uit), and a legal duty for government websites and apps since 1 July 2018 | Checks colour contrast at WCAG AA when you upload a set, for every shipped set and for each generated dark variant. Ships a high-contrast set that follows the operating system's forced-colours mode. Produces a [contrast evidence report](./compliance-report.md) for your accessibility statement. |

Thematiq supports the colour and contrast part of Digitoegankelijk. It does not make an instance conform: that also depends on every app, on your content and on an expert WCAG-EM evaluation.

The other standards on the list cover data exchange, security and documents. Thematiq exchanges no data between organisations and stores no documents, so they do not apply to it.

### Design and accessibility standards

| Standard | What Thematiq does | Limits |
|---|---|---|
| NL Design System | 38 house styles under the `--nldesign-*` token names. A nightly workflow proposes upstream changes. | See [token sets](./token-sets.md) for how sets are added. |
| Rijkshuisstijl | The `rijkshuisstijl` set carries the official colour palette. Fira Sans stands in for the proprietary RijksoverheidSans. | No Rijkslogo ships. See the [Rijkshuisstijl checklist](../reference/compliance.md). |
| WCAG 2.1 AA, SC 1.4.3 and 1.4.11 | Contrast checks at 4.5:1 for text and 3:1 for UI parts. In the [shipped audit](./contrast-audit.md), 47 of 50 sets pass. | `vng` and `noaberkracht` fail, and `conduction-new` cannot be evaluated. See the [contrast report](../reference/contrast-report.md). |
| WCAG 2.2 AA, SC 1.4.3 and 1.4.11 | The contrast evidence report classifies 18 colour pairs of the live theme. | Colour contrast only. The report says it is not a full audit. |
| WCAG AAA, SC 1.4.6 | The [high-contrast set](./high-contrast.md) reaches 21:1 for primary text. | One set only. |
| W3C Design Tokens format | [Custom token sets](./custom-token-sets.md) accept W3C Design Tokens JSON as well as CSS. | A community group format, not a W3C recommendation. |

### GEMMA

Thematiq maps to no GEMMA reference component. GEMMA describes the functions of a municipal application landscape, such as case handling or document management. A house style is not one of those functions.

Thematiq is cross-cutting instead. It sits under every Conduction app on a Nextcloud server and gives them all the same house style and contrast checks. When you map your landscape onto GEMMA, list the apps that do the work, not Thematiq.

### TEC

Other Conduction apps describe their scope with the TEC feature framework, for example TEC-DMS codes for document management. No TEC framework covers a theming layer, so Thematiq carries no TEC codes.

## Feature index

### Choose a house style

| Feature | What you do with it | Standards | Page |
|---|---|---|---|
| Token sets | Pick one of 51 sets in a searchable list | NL Design System, Rijkshuisstijl | [Token sets](./token-sets.md) |
| Apply dialog | See every token that changes and untick the ones you want to keep | | [Apply dialog](./apply-dialog.md) |
| Theming sync | Copy the set's colours and logo into Nextcloud's own theming | | [Theming sync](./theming-sync.md) |
| Custom token sets | Upload your own set as CSS or W3C Design Tokens JSON, or build one from your colours | WCAG 2.1 AA | [Custom token sets](./custom-token-sets.md) |
| Theme gallery | Install a house style another organisation built | | [Theme gallery](./theme-gallery.md) |
| Theme preview | Try a set in your own session before anyone else sees it | | [Theme preview](./theme-preview.md) |
| Planned switches | Switch the set on a date, and back again | | [Plan a theme switch](./plan-a-theme-switch.md) |
| Command line | List, read and switch the set with `occ` | | [Command line](./command-line.md) |
| La Suite stack | Match the French La Suite numérique apps, with Marianne behind a gate | | [La Suite](./la-suite.md) |
| Summer Breeze | Use a soft, airy theme | | [Summer Breeze](./summer-breeze.md) |

### Tune it

| Feature | What you do with it | Standards | Page |
|---|---|---|---|
| Token editor | Change any Nextcloud colour token with a live preview | | [Token editor](./token-editor.md) |
| Toggles and theming per app | Hide the login slogan, show menu labels, leave single apps unthemed | | [Toggles](./toggles.md) |
| Group theming | Give each Nextcloud group its own set | | [Group theming](./group-theming.md) |
| Dark mode | Serve a contrast-checked dark variant of every set | WCAG 2.1 AA | [Dark mode](./dark-mode.md) |
| High contrast | Offer a set for users who need stronger contrast | WCAG AAA, EN 301 549 | [High contrast](./high-contrast.md) |
| Custom fonts | Upload your licensed WOFF2 fonts, self-hosted | | [Custom fonts](./custom-fonts.md) |
| Email template | Brand system emails and link your accessibility statement | | [Email template](./email-theming.md) |
| Environment marker | Mark test and acceptance servers on every page | | [Environment marker](./environment-marker.md) |
| Icons | Reuse government icons and logos in any app | | [Icons](../reference/icons.md) |

### Prove and govern it

| Feature | What you do with it | Standards | Page |
|---|---|---|---|
| Shipped contrast audit | See how every shipped set scores on contrast | WCAG 2.1 AA, AAA | [Contrast audit](./contrast-audit.md) |
| Contrast evidence report | Download contrast evidence for the live theme | WCAG 2.2 AA, Digitoegankelijk | [Contrast evidence](./compliance-report.md) |
| Audit log | See who changed the theme, and when | | [Audit log](./audit-log.md) |
| Versions | Put an earlier configuration back | | [Restore a version](./restore-a-version.md) |
| Overrides import and export | Save and reload your token overrides as a CSS file | | [Import and export](./import-export.md) |
| Configuration bundle | Move the whole configuration from test to production | | [Configuration bundle](./configuration-bundle.md) |
| Upstream updates | Hear when NL Design System house styles change upstream | NL Design System | [Upstream updates](./upstream-updates.md) |

### Run and integrate it

| Feature | What you do with it | Standards | Page |
|---|---|---|---|
| Health and metrics | Probe the app and scrape Prometheus metrics | | [Monitor Thematiq](./metrics-and-health.md) |
| CSS architecture | Understand the layer order from design system to your overrides | | [CSS architecture](./css-architecture.md) |
| App compatibility | Make your own Nextcloud app follow the theme | | [App compatibility](./app-compatibility.md) |
| Public portals | Reuse the token sets on a portal outside Nextcloud | | [Public portals](./public-portals-as-consumers.md) |
| Admin settings | Find every setting on one page | | [Admin settings](./admin-settings.md) |

Two shipped features have no page yet: freeform custom CSS in the **Custom CSS** section, and **Let the primary color drive every component**. Both sit in the admin settings.

## Start here

New to Thematiq? Pick a set in [token sets](./token-sets.md), then try it on yourself with a [theme preview](./theme-preview.md).
