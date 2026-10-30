## ADDED Requirements

### Requirement: a week strip item card keeps its own ink

A CnWeekStripWidget item (`a.cn-week-strip__item`) MUST NOT be painted by a forced link colour rule and MUST NOT be underlined, in any state; its frame marks it as a control.

#### Scenario: DqMijnWerk Termijnen deze week
- GIVEN the case "Parkeervergunningen binnenstad" in Wednesday's column
- THEN its title reads in the body ink, not underlined
