---
sidebar_position: 16
---

# Environment marker

A test server that looks exactly like production invites mistakes: someone files real work in the test copy, or tries a destructive action on live data. Thematiq can mark every page of a development, test or acceptance server with a coloured stripe and a text label, so you always see where you are.

## Declare the environment

The environment belongs to the server, not to the database. Set it in `config.php`:

```bash
occ config:system:set thematiq.environment --value=test
```

Or in `config.php` directly, for example from your Helm values or deployment scripts:

```php
'thematiq.environment' => 'acceptance',
```

The allowed values are `development`, `test`, `acceptance` and `production`. A database restored from production into test keeps the test label, because the value lives in `config.php`. The configuration bundle never carries it either: it is the one value that must differ between environments.

## What users see

| Value | Label | Title prefix |
|---|---|---|
| `development` | Development environment | [Development] |
| `test` | Test environment | [Test] |
| `acceptance` | Acceptance environment | [Acceptance] |
| `production` or not set | nothing | nothing |
| anything else | Unknown environment | [Unknown] |

The stripe sits at the top of every page, including the login page, public share pages and apps you excluded from theming. The label is text, so it does not rely on colour alone, and it is the first note a screen reader announces. The stripe colours are fixed and do not follow your house style, so a brand colour can never hide it.

A value outside the list shows "Unknown environment" and writes a warning to the Nextcloud log naming the value, so a typo never makes a server look like production.

## Check it

Settings > Administration > Theming shows the environment the server declares, or the command to set it when none is set. The page never changes the value itself.
