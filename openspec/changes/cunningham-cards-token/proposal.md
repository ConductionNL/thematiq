# Proposal: cunningham-cards-token

## Why

Ruben's decision: the nldesign sets whose palette follows Cunningham drop the line under dashboard card titles too,
outside the board look, decided from data rather than by naming sets in a stylesheet. The sets carried no marker that
they follow Cunningham (their token files only say so in comments).

## What changes

- A set marks that it follows Cunningham's cards with a token in its own token file:
  `--nldesign-component-dashboard-card-header-border-style: none`. zuiddrecht, vaartveld, wilgenboom, esdoornveen and
  warmtepompacademie declare it; their dark variants and token reference pages are regenerated.
- The nldesign layer reads it for a nextcloud-vue dashboard card's header:
  `border-bottom-style: var(--nldesign-component-dashboard-card-header-border-style, solid)`. A set that declares
  nothing keeps the card's own rule, and the width and colour stay the card's. The Nextcloud dashboard chrome and a
  chromeless card are left alone, as in the Cunningham layer's own rule (cunningham-dashboard-card-header-rule).

## Reach

Under the five sets, every nextcloud-vue dashboard loses the rule under card titles, in the board look and the
Nextcloud look alike. Measured under Zuiddrecht with the change served in place: OpenRegister's dashboard (Nextcloud
look) and pipelinq's werkplek go from a 1px rule to none; dossiq's board dashboard already had none. Other sets do not
change.
