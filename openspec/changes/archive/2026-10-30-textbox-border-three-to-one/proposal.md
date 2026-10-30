# Proposal: textbox-border-three-to-one

## Why

Under the nldesign system a text field's edge fell back to the hairline border (`--nldesign-color-border`), which in
most sets is 1.2 to 1.5:1 against the field. WCAG 1.4.11 asks 3:1 for the boundary of a control. The board search field
under Zuiddrecht drew #E4E6EA, 1.27:1. Fifteen sets also shipped a field border or control step of their own under 3:1.

## What changes

- The nldesign defaults and the utrecht bridge give `--nldesign-component-textbox-border-color` the control step,
  `--nldesign-color-border-dark`, in every set that does not declare its own field border.
- The converter's contrast repair table measures the control step against the page and the set's own field borders
  (`--utrecht-textbox-border-color`, `--utrecht-form-input-border-color`) against the field at 3:1, and moves a value
  that misses by lightness only. A set's own border that passes is kept.
- The same repair is applied to the shipped sets whose values miss today (borne, buren, conduction-new, drechterland,
  duiven, enkhuizen, leidschendam-voorburg, noordoostpolder, stedebroec, tilburg, utrecht, westervoort, xxllnc,
  zaanstad). The dark variants are regenerated.
