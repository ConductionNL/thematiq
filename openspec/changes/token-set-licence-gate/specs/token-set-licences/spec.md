# token-set-licences

## ADDED Requirements

### Requirement: A token set declares its licence

A shipped token set MAY declare its licence in `token-sets.json` under `licence`, with `id` (an SPDX
identifier or a `LicenseRef-` name), `package` (the source package), `version` (the version of that
package the set was made from), `holder` (who the house style belongs to) and `restricted` (whether
the licence limits use to that holder). The full licence text MUST ship beside the set as
`css/tokens/<id>.LICENSE.md`. A set with an open licence declares it too, with `restricted: false`.

A `licence` block whose `restricted` is anything other than the literal `false` MUST count as
restricted. Only the shipped manifest is read: an uploaded set (`custom-*`) declares nothing and is
never restricted.

@e2e exclude manifest reading — what a set declares is read from a file on the server; proven by tests/Unit/Service/RestrictedSetGateTest.php.

#### Scenario: A restricted set carries its source and its text

- GIVEN the manifest entry of `owned` declares package `@example/voorbeeld-design-tokens`, version `1.0.3`, holder `Gemeente Voorbeeld` and `restricted: true`
- AND `css/tokens/owned.LICENSE.md` holds the licence text
- WHEN the licence of `owned` is read
- THEN it names that package, version and holder, it is restricted, and the full text is returned

#### Scenario: A licence block without the flag is restricted

- GIVEN the manifest entry of a set declares a `licence` without `restricted`
- WHEN the set is checked
- THEN it counts as restricted

#### Scenario: An open licence and an uploaded set are not restricted

- GIVEN `open` declares `restricted: false` and `custom-mine` is an uploaded set
- WHEN both are checked
- THEN neither is restricted

### Requirement: A restricted set is refused until an administrator confirms it

The server MUST refuse to apply a restricted set that has no confirmation. The refusal MUST reach the
client as HTTP 403 with `error: "restricted_set_not_confirmed"` and the set id, and MUST write nothing.
Hiding the set in the settings page is not the refusal: a direct request is refused the same way.

@e2e exclude delivered status — no shipped set is restricted until the shipped sets are audited, so a browser has nothing to be refused on; the status that goes out is proven through Nextcloud's own Dispatcher and middleware chain by tests/Unit/Controller/RestrictedSetRequestPathTest.php.

#### Scenario: A direct request for an unconfirmed set gets a 403

- GIVEN `owned` is restricted and nobody confirmed it
- WHEN an administrator posts `owned` to `/settings/tokenset`
- THEN the response status is 403 with `error: "restricted_set_not_confirmed"` and `tokenSet: "owned"`
- AND the active token set is unchanged

#### Scenario: The same request succeeds after the confirmation

- GIVEN an administrator confirmed `owned`
- WHEN an administrator posts `owned` to `/settings/tokenset`
- THEN the response status is 200 and `owned` is the active token set

#### Scenario: An open-licensed set needs no confirmation

- GIVEN `open` declares `restricted: false`
- WHEN an administrator applies `open`
- THEN it is applied

### Requirement: An administrator confirms a restricted set

`POST /settings/tokensets/{id}/licence/confirmation` MUST store a confirmation for that set only when
the body carries `confirmed: true`. The confirmation MUST record the administrator's user id and the
time, per set. It MUST write a `restricted_set_confirmed` entry to the theming audit trail (actor,
set, licence identifier, holder) and a line to the Nextcloud log. A confirmation of one set MUST NOT
count for another. A second confirmation of a confirmed set keeps the first. A set that is not
restricted, or does not exist, answers 404; a request without the statement answers 400.

@e2e exclude confirmation record — who confirmed and when is state written behind the request; proven by tests/Unit/Service/TokenSetLicenceServiceTest.php and tests/Unit/Controller/TokenSetLicenceControllerTest.php.

#### Scenario: The confirmation is stored and logged

- GIVEN `owned` is restricted
- WHEN administrator `ruben` posts `confirmed: true` for `owned`
- THEN the confirmation of `owned` records `ruben` and the time
- AND the audit trail holds a `restricted_set_confirmed` entry with actor `ruben` and set `owned`

#### Scenario: A request without the statement confirms nothing

- GIVEN `owned` is restricted
- WHEN an administrator posts to the confirmation of `owned` without `confirmed: true`
- THEN the response status is 400 and nothing is stored

#### Scenario: A confirmation counts for its own set

- GIVEN `owned` is confirmed and `owned-two` is not
- WHEN an administrator applies `owned-two`
- THEN it is refused

### Requirement: A confirmation can be withdrawn once the set is no longer applied

`DELETE /settings/tokensets/{id}/licence/confirmation` MUST remove the confirmation and write a
`restricted_set_confirmation_withdrawn` audit entry naming who withdrew and whose confirmation ended.
While the set is applied anywhere (as the instance's set, for a group, as an app's brand, or in a
planned or running switch, including the set a running switch returns to) the withdrawal MUST be
refused with HTTP 409 and the list of those places. Withdrawing never un-applies a set.

@e2e exclude withdrawal — needs a restricted shipped set, which this change does not add; proven by tests/Unit/Service/TokenSetLicenceServiceTest.php.

#### Scenario: A withdrawal while the set is active is refused

- GIVEN `owned` is confirmed and is the active token set
- WHEN an administrator withdraws the confirmation
- THEN the response status is 409 and names the instance
- AND the confirmation and the active set are unchanged

#### Scenario: A withdrawal of an unused set is logged

- GIVEN `owned` is confirmed by `ruben` and applied nowhere
- WHEN administrator `noor` withdraws the confirmation
- THEN `owned` is refused again
- AND the audit trail holds a `restricted_set_confirmation_withdrawn` entry with actor `noor` and `confirmedBy: ruben`

### Requirement: Every path that applies a set asks the same gate

Each of these MUST refuse an unconfirmed restricted set and write nothing: the instance-wide switch
(settings page and `occ thematiq:theme:set`), a group mapping, a subadmin's delegated choice, the start
and the publication of a preview, planning a switch and the start of a planned switch, a brand per
app, and a configuration bundle that names the set as active set, in a planned switch or as an app's
brand. A confirmation does not travel in a bundle. A preview of a set whose confirmation is gone MUST
NOT be drawn.

A place that already holds the set MAY keep it: saving the same group mapping again, choosing the
active set again, or importing a bundle that names the set active here, is not a new use.

@e2e exclude wiring — each path is asserted from its caller over the real services, and removing the gate call from any one of them turns the suite red; proven by tests/Unit/Service/RestrictedSetWiringTest.php and tests/Unit/Controller/RestrictedSetRequestPathTest.php.

#### Scenario: A group mapping with an unconfirmed set is refused whole

- GIVEN `owned` is restricted and unconfirmed
- WHEN an administrator saves a mapping that gives group `ruimte` the set `owned`
- THEN the response status is 403 and no entry of the mapping is stored

#### Scenario: A planned switch does not start without the confirmation

- GIVEN a switch to `owned` was planned while `owned` was confirmed, and the confirmation is gone
- WHEN the switch is due
- THEN the active token set is unchanged and the switch is marked failed with the reason

#### Scenario: A bundle that names the set is refused

- GIVEN `owned` is restricted and unconfirmed on this server
- WHEN a bundle that names `owned` as an app's brand is imported
- THEN the import is refused and names `owned`

#### Scenario: A group that keeps its set is saved again

- GIVEN group `ruimte` holds `owned`, which is restricted and unconfirmed
- WHEN an administrator saves the mapping with `ruimte` still on `owned` and a new group on `open`
- THEN the mapping is stored
- AND giving a second group `owned` is refused

### Requirement: The picker shows the warning and the licence text

The admin settings, where token sets are chosen, MUST list every restricted set with a warning that
names the holder, the source package and version, the full licence text, and its status. An
unconfirmed set shows the statement "We are this organisation, or we have its permission to use this
house style." with a checkbox; the confirm button works only when it is ticked. A confirmed set shows
who confirmed and when, and a way to withdraw. Choosing an unconfirmed set in the token set dropdown
MUST point to its confirmation. The texts ship in Dutch and English.

@e2e exclude licence block — no shipped set is restricted in this change, so the block is hidden on a real instance; proven by tests/vitest/admin-set-licence.spec.js. The browser check belongs to the change that marks the shipped sets.

#### Scenario: A restricted set shows its warning and text

- GIVEN `owned` is restricted, with holder `Gemeente Voorbeeld`
- WHEN an administrator opens `owned` under House styles of other organisations
- THEN the warning names Gemeente Voorbeeld, the source package and version are shown, and the licence text is shown in full

#### Scenario: The confirm button needs the statement

- GIVEN `owned` is unconfirmed
- WHEN the statement is not ticked
- THEN the confirm button is disabled

### Requirement: A set applied before it was restricted stays applied and asks for confirmation

A restricted set that is applied without a confirmation MUST stay applied: nothing un-applies it. The
admin settings MUST show a notice that names the set, says that it stays applied, and offers the
confirmation. The notice is gone once the set is confirmed or no longer applied.

@e2e exclude awaiting notice — needs an instance that applied a set before it became restricted; proven by tests/Unit/Service/TokenSetLicenceServiceTest.php and tests/vitest/admin-set-licence.spec.js.

#### Scenario: An applied, unconfirmed set is announced and kept

- GIVEN `owned` is the active token set and nobody confirmed it
- WHEN an administrator opens the admin settings
- THEN a notice says that Gemeente Voorbeeld is in use without a confirmation and stays applied
- AND `owned` is still the active token set
