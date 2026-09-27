# Spec delta: token set contrast audit (authoring-token-value-types)

## ADDED Requirements

### Requirement: Translucent colours are measured as they render

`ContrastService` MUST read `#rgba`, `#rrggbbaa`, and the alpha of `rgba()` and `hsla()`. Before a
ratio is computed, a translucent foreground MUST be blended over its background, and a translucent
background over the page background (`--nldesign-color-background`, else white). This MUST stay
inside the one contrast implementation this spec requires, and a colour that cannot be parsed MUST
stay `unevaluated`.

#### Scenario: Faint text fails even though its opaque colour would pass
@e2e exclude Contrast arithmetic, covered by PHPUnit on ContrastService
- GIVEN text `rgba(0, 0, 0, 0.2)` on background `#ffffff`
- WHEN the pair is evaluated
- THEN the ratio MUST be computed for the blend `#cccccc` on `#ffffff`
- AND the pair MUST fail AA

#### Scenario: An 8-digit hex is evaluated, not skipped
@e2e exclude Contrast arithmetic, covered by PHPUnit on ContrastService
- GIVEN text `#000000cc` on background `#ffffff`
- WHEN the pair is evaluated
- THEN the pair MUST get a ratio and a verdict
- AND it MUST NOT be `unevaluated`
