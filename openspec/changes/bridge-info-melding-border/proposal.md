## Why

The school portal boards draw an info melding as a tinted card without a line ("Online melden" on
the De Wilgenboom content page; the same shape on Vaartveld, Esdoornveen and Warmtepompacademie).
The bridge gives every melding `--utrecht-alert-border-width` (2px unless the set names
`--nldesign-website-notice-border-width`), so the portal draws the card with a 2px line in the
primary. A set has no way to say "no line on an info melding" without also removing the line from
the plain notice and the other kinds. (School portal proof, 06 Oct; portaliq #1363 gives the
melding its tint and padding.)

## What Changes

- `css/public-bridge.css`: a new vocabulary token `--nldesign-website-alert-info-border-width`,
  carried by `--utrecht-alert-info-border-width`. Unset, the role falls back to
  `--utrecht-alert-border-width`, so every portal looks exactly as before.
- `tests/vitest/publicBridgeRoleLayer.spec.js`: the role equals the general width for the four
  schools and Zuiddrecht, and becomes 0 when a set names the token while the other kinds keep
  their width.

## Not in this change

The four school token files (`css/tokens/<school>.css`) belong to the theme lane. To match the
boards each sets `--nldesign-website-alert-info-border-width: 0`.

## Impact

Portal roles only (`css/public-bridge.css` is linked by the portal site, never by an instance
page). No visible change until a set names the token.
