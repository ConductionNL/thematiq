# Spec delta: simple brand form (brand-form-action-colour)

## ADDED Requirements

### Requirement: The form may give the main button its own colour

The form MUST offer one box, not ticked by default, labelled "Give the main button its own color",
with a colour field that is disabled until the box is ticked. With the box ticked the derived set
MUST hold `--nldesign-color-action` (the chosen colour), `--nldesign-color-action-hover` (that
colour 15% darker) and `--nldesign-color-action-text` (black or white, whichever contrasts better).
The preview MUST show the main button in the action colour and the readout MUST add the contrast of
the text on it. With the box not ticked the derived set MUST hold no action token and MUST be the
set the form made before. The browser and the server MUST derive the same values. A value that is
not a hex colour MUST be refused with 400.

#### Scenario: A green main button on a blue house style
@e2e exclude Form wiring and derivation, covered by vitest (admin-brand-form, brandForm) and PHPUnit (BrandFormServiceTest)
- GIVEN an administrator on "Start from your colors" with primary `#1a67a3`
- WHEN they tick "Give the main button its own color" and choose `#1f883d`
- THEN the sample button MUST be `#1f883d`
- AND the created set MUST declare `--nldesign-color-action: #1f883d`
- AND its header MUST stay `#1a67a3`

#### Scenario: The box is not ticked
@e2e exclude Derivation, covered by PHPUnit (BrandFormServiceTest) and the parity fixture
- GIVEN an administrator who leaves the box alone
- WHEN the house style is created
- THEN the set MUST declare no action token
