# Design

## Where the gate sits

Every service that can apply a set already holds `TokenSetService`, because each of them checks that
the set exists. `TokenSetService::licenceGate()` hands out the gate, so no constructor changes and no
service can be built without it. A call site asks `assertMayApply()`, which throws
`RestrictedSetNotConfirmedException`. That exception extends `\InvalidArgumentException`, the type every
one of these paths already refuses on: a caller nobody updated treats a restricted set as one it may
not apply.

The gate works at write time, not at render time. A page render never drops a theme because of a
licence; only applying a set is refused.

## A set that is already there stays

`assertMayApply($set, $alreadyApplied)` lets a set through when the place being written already holds
it. Saving a group mapping again, or choosing the active set a second time, is not a new use. This is
what keeps an instance from losing its theme when a set it applied earlier becomes restricted. Applying
that set in any new place still needs the confirmation.

One limit: a running planned switch that has to go back to an unconfirmed restricted set does not go
back. It is marked as it is today when its set is gone, and the settings page lists that set as
awaiting confirmation for as long as the switch runs.

## Withdrawing

Withdrawing a confirmation is refused with 409 while the set is applied anywhere (instance, group,
app brand, an open planned switch). The alternative, un-applying the set, would change what users see
as a side effect of a checkbox. The administrator chooses another set first; the answer names where.

## Previews

A preview draws the set on this instance, for the administrator. That is a use, so a preview of a
restricted set needs the confirmation too. The warning and the full licence text are readable without
it.

## What is not gated

Uploaded sets (`custom-*`) carry no declaration: the administrator who uploads a set answers for it.
Only the shipped manifest can declare a licence, so an upload cannot declare itself open.

## Status code

The controllers are plain `Controller` classes, so a 403 reaches the browser as 403. Nextcloud's
OCSMiddleware would answer 200 for an OCSController. `RestrictedSetRequestPathTest` sends each request
through Nextcloud's own Dispatcher and middleware chain, with a control that shows the same chain
turning an OCS 403 into 200.

## Audit vocabulary

Two actions join the closed vocabulary of the theming audit trail: `restricted_set_confirmed` and
`restricted_set_confirmation_withdrawn`.
