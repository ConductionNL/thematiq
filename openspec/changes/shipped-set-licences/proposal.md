# Shipped set licences

## Why

Change `token-set-licence-gate` lets a set declare its licence and keeps a restricted set off until an
administrator confirms it. No shipped set declared anything yet. The audit of 14 October 2026 read the
licence of every shipped set at its source: 44 of the 63 sets copy an organisation's house style under
a licence that limits use to that organisation (decisions 232 and 237).

## What changes

- 44 sets declare a restricted licence and ship the text as `css/tokens/<id>.LICENSE.md`. Four declare
  an open one (`denhaag` EUPL-1.2; `lasuite`, `frankendesk`, `cunningham` MIT). The audit table is
  `docs/reference/token-set-licences.md`.
- A repair step records, after an upgrade, each restricted set that is applied without a confirmation.
  It un-applies nothing. The admin settings show the notice with the confirm action.
- Version 1.2.28, so the repair step runs.

## Impact on upgrade

An instance that applied one of the 44 sets keeps it: for the whole instance, a group, an app's brand
or a running switch. The upgrade log names the set. The admin settings ask for the confirmation.
Until then the set cannot be applied in a new place, previewed or planned.
