# cunningham-cards-token Delta

## ADDED Requirements

### Requirement: A set that follows Cunningham's cards drops the dashboard card header rule

A token set SHALL be able to mark that it follows Cunningham's cards by declaring
`--nldesign-component-dashboard-card-header-border-style: none`; under the nldesign system a nextcloud-vue dashboard
card SHALL then draw no rule under its title. A set that declares nothing SHALL keep the card's own rule.

#### Scenario: OpenRegister's dashboard under Zuiddrecht

- **GIVEN** the Zuiddrecht set, which declares the marker
- **THEN** OpenRegister's dashboard cards draw no line under their titles
