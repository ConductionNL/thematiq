---
sidebar_position: 21
---

# Document house style

Letters and PDF exports should look like the rest of the house style. Thematiq does not render documents. It publishes the values a document needs, and the app that generates the document lays them out: filinq for templates, OpenRegister for PDF exports.

## The profile

```json
{
  "tokenSet": { "id": "bussum", "name": "Gemeente Bussum" },
  "organisation": "Gemeente Bussum",
  "logo": { "url": "/apps/thematiq/api/document-style/logo?v=1790000000", "mime": "image/png" },
  "cover": null,
  "colours": {
    "primary": "#004b87",
    "primaryText": "#ffffff",
    "text": "#1a1a1a",
    "background": "#ffffff",
    "accent": "#e5eef6"
  },
  "fonts": {
    "heading": { "family": "Corporate", "url": "/apps/thematiq/fonts/custom-corporate.woff2" },
    "body": { "family": "'Fira Sans', sans-serif", "url": null }
  },
  "footer": {
    "lines": ["Gemeente Bussum", "Postbus 1, 1400 AA Bussum"],
    "accessibilityUrl": "https://www.bussum.nl/toegankelijkheid",
    "privacyUrl": "https://www.bussum.nl/privacy"
  },
  "warnings": []
}
```

- `tokenSet`: the set that applies to the user. A user in a mapped group gets the group's set, so one shared Nextcloud produces letters per municipality.
- `logo`: the document logo uploaded in the Documents block, else the logo of that set, else `null`.
- `cover`: the cover image from the Documents block, or `null`.
- `colours`: from the set's resolved tokens. `background` is the paper, white unless the set declares one.
- `fonts`: an uploaded font for the role, with its file URL; otherwise the family the set declares and `url: null` (a system font).
- `footer`: the email footer organisation name, the extra footer line from the Documents block, and the accessibility and privacy links of the email footer.
- `warnings`: `[{ "code": "text-contrast", "ratio": 2.9 }]` when the text colour does not reach 4.5:1 on the background (`ratio` is `null` when the colours cannot be measured).

Escape every value when you put it in HTML. The extra footer line is free text.

## Read it in-process

From another app on the same server, when thematiq is installed:

```php
$style = null;
if ($appManager->isEnabledForUser('thematiq')) {
	$style = \OCP\Server::get(\OCA\Thematiq\Service\DocumentStyleService::class);
}
$profile = $style?->forUser($uid);
```

`forUser(null)` returns the instance default house style. Treat a missing class as "no house style", never as an error.

## Read it over HTTP

```
GET /apps/thematiq/api/document-style
GET /apps/thematiq/api/document-style/logo
GET /apps/thematiq/api/document-style/cover
```

Signed-in users only; without a session Nextcloud refuses the request. The profile is the same as `forUser()` for the signed-in user.

## Settings

Settings > Administration > Theming, block Documents: a document logo and a cover image (PNG, JPEG, WebP or SVG, at most 2 MB; an SVG with script or event handlers is refused) and one extra footer line of at most 200 characters. The footer line travels in the configuration bundle as `documentStyle.footerLine`; the images travel as metadata only, like fonts, and are uploaded again on the target server.
