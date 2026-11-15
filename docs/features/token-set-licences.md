---
sidebar_position: 40
---

# House styles of other organisations

Some token sets copy the house style of a real organisation. That house style belongs to that organisation. Its licence says who may use it, and often that is only the organisation itself.

Thematiq ships each of these sets with its licence text. A set whose licence limits use to its owner is switched off until you confirm that you may use it.

## Read the licence

Open Settings > Administration > Theming and find **House styles of other organisations**, under the token set dropdown. Each restricted set has its own row. Open a row to see:

- who the house style belongs to
- the package and version the set was made from
- the full licence text

## Confirm that you may use a set

1. Open the row of the set.
2. Read the licence text.
3. Tick **We are this organisation, or we have its permission to use this house style.**
4. Click **Confirm**.

Your name and the time are recorded in the [audit log](./audit-log.md). From then on you can apply the set like any other.

A confirmation counts for one set. Each restricted set needs its own.

## What happens without a confirmation

You cannot apply the set. That holds everywhere a set is chosen:

- the token set dropdown and the preview in your session
- a house style per group, also when a group manager chooses
- a brand per app
- a planned switch
- a configuration bundle you import
- `occ thematiq:theme:set`

The server refuses the request, so a script or another tool cannot apply the set either.

A confirmation does not travel in a configuration bundle. Confirm the set on each server that uses it.

## Withdraw a confirmation

Open the row and click **Withdraw confirmation**. This only works when the set is no longer in use. If it is, the message names where. Choose another set there first.

Withdrawing never changes what your users see.

## A set you applied before it was restricted

The set stays applied. The settings page shows a notice that names the set and asks for the confirmation. Nothing changes for your users until you choose another set yourself.

You cannot apply that set in a new place, such as another group, until you confirm it.

## Sets you upload yourself

A set you upload carries no licence in Thematiq. You answer for the house style you upload.
