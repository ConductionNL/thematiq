---
sidebar_position: 22
---

# Read token deprecations from your app

Your app reads the house style through CSS variables. When an administrator deprecates one of them, your app can find out before the token is removed, and switch to its replacement in time.

## The endpoint

Ask Nextcloud as the signed-in user:

```
GET /apps/thematiq/api/token-deprecations
```

Any signed-in user may read it; a request without a session is refused. The answer is the same for every user and holds token names and dates only.

```json
{
  "deprecations": [
    {
      "token": "--nldesign-org-old-accent",
      "severity": "warning",
      "replacement": "--nldesign-org-brand-accent",
      "removalDate": "2027-03-01",
      "message": "Use the new brand accent",
      "deprecatedAt": "2026-10-02T09:00:00+00:00",
      "due": false,
      "state": "active"
    }
  ]
}
```

- `severity` is `info`, `warning` or `critical`.
- `replacement`, `removalDate` and `message` are `null` when the administrator left them out.
- `due` is `true` once the removal date has passed. The token still works then; only the administrator removes it.
- `state` is `removed` when the administrator removed the token. The record stays so you can see what happened.

## What to do with it

Read the list once when your app starts, not on every render. For each token your app uses:

1. When it has a `replacement`, read that token instead, with the old one as a fallback: `var(--nldesign-org-brand-accent, var(--nldesign-org-old-accent))`.
2. When it is `due` or `removed`, log it for your developers, so the next release drops it.

Next, check which tokens your app reads, and request the list from your test instance.
