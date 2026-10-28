# A week strip item keeps its own ink

## Why
nextcloud-vue's CnWeekStripWidget draws each case of the week as a framed card that is a link. The NL Design link rules forced every such card's text into the link colour and underlined it (DqMijnWerk "Termijnen deze week": titles blue and underlined, where the board draws them in the body ink).

## What changes
`a.cn-week-strip__item` joins the links the link colour and underline rules leave out, as a link drawn as a button already does, in the NL Design and high-contrast sheets.
