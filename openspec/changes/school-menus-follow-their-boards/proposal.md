## Why

Proof run 3 (09 Oct): the resident menu of the four school portals used the Zuiddrecht board's rows (60px,
a 4px accent bar beside the page on screen, ink text). The MijnMenu boards draw 44 to 48px rows, no bar,
and the page on screen in the accent's text colour (Wilgenboom: ink). portaliq's site theme now reads these
three values as set tokens, with the Zuiddrecht values as defaults (portaliq
`resident-menu-follows-the-boards`, PR #1423).

## What Changes

The four school sets name, in their website section:

- `--nldesign-website-menu-item-min-block-size`: wilgenboom 46px, vaartveld 44px, esdoornveen 44px,
  warmtepompacademie 48px;
- `--nldesign-website-menu-current-bar-width`: 0;
- `--nldesign-website-menu-current-color`: the accent's text colour, Wilgenboom the text colour.

## Impact

Read only by portaliq's site theme. Zuiddrecht and every other set keep the defaults. No colour value is
new: the colour token points at an existing one, so contrast is unchanged (7.36:1 or more, see the sets).
