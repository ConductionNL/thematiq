---
sidebar_position: 20
---

# Approved mark for the AI assistant

An organisation can mark the AI assistant in Conduction apps as the one it approved. Thematiq owns the statement: whether the mark is on, with which name and logo. The assistant panel in `@conduction/nextcloud-vue` draws it. This page is the contract between the two.

## Settings

Settings > Administration > Theming, block AI assistant:

| Setting | App config key | Default |
|---|---|---|
| Show the approved mark | `assistant_mark_enabled` (`1` or `0`) | off |
| Organisation name | `assistant_mark_organisation` | the email footer organisation name |
| Logo address | `assistant_mark_logo` (an https address or a path on this server) | the logo of the active house style (`logos.default` of the theming capability) |

The mark cannot be turned on while both the organisation name and the email footer name are empty. The three values travel in the configuration bundle as `assistantMark`.

The mark informs honest users which assistant is the sanctioned one. It is not a security control: a page an attacker controls can draw anything.

## Endpoint

```
GET /apps/thematiq/api/assistant-mark
```

Signed-in users only; without a session Nextcloud refuses the request. The answer is in the requesting user's language.

Mark off:

```json
{ "enabled": false }
```

Mark on:

```json
{
  "enabled": true,
  "label": "Approved by Gemeente Voorbeeld",
  "organisation": "Gemeente Voorbeeld",
  "logo": { "url": "/apps/thematiq/img/logos/voorbeeld.svg", "alt": "Gemeente Voorbeeld logo" }
}
```

`logo` is `null` when neither an own logo nor a house style logo exists.

## What the panel does

- Call the endpoint once per session, when thematiq is installed. Caching for the session is fine.
- Mark on: show the logo with its `alt` and the `label` in the panel footer. The label is already translated; do not translate it again.
- Mark off, thematiq not installed, or the request fails: show nothing in that place.
