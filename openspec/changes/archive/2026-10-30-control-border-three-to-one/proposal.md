# Proposal: control-border-three-to-one

## Why

The Zuiddrecht-type sets (zuiddrecht, vaartveld, wilgenboom, esdoornveen, warmtepompacademie) gave controls the border
#8A929E, which is 3:1 only against white. A board control also sits on the page ground #F5F6F8, the table head, the bulk
band #EAF0F7 and the switch ground #ECEEF1, where #8A929E is 2.70 to 2.90. The design system (design-system #306) moved
the boards' button border to #828A95, which is at least 3:1 on all of them.

## What changes

`--nldesign-color-border-dark` in those five sets is #828A95. It feeds `--color-border-dark` and
`--color-border-maxcontrast`, so every control border that reads them follows, the board look's buttons included. The
dark variants are regenerated from the light sets, and the shell catalogue's `field` role and the token reference follow.
