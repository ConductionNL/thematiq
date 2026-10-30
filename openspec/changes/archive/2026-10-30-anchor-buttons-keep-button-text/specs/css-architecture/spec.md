## ADDED Requirements

### Requirement: A link drawn as a button reads as a button

An anchor drawn as a button (`a.button-vue`, `a.button`, `a[role="button"]`,
`a.cn-banner-widget__action`, `a.cn-link-button-widget__button`) SHALL keep its
button's own text colour for every variant and SHALL NOT be underlined, in any
state (rest, hover, focus, visited), under every nldesign set and the
high-contrast system. A link in prose SHALL keep the link colour and the set's
link decoration.

#### Scenario: the Vandaag eerst card on Mijn werk
- GIVEN dossiq's Mijn werk card "Vandaag eerst" with the link actions "Open deze zaken", "Naar het werkbord", "Zaak openen" and the button "Termijn opschorten"
- WHEN it renders under the Zuiddrecht set
- THEN no action text is underlined
- AND the outlined actions read in the main ink, like "Termijn opschorten"
- AND the primary action "Zaak openen" reads white on the primary fill
