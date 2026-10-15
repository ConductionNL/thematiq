# Spec delta: shell pixel check (shell-pixel-check)

## ADDED Requirements

### Requirement: The shell is compared with reference images

An opt-in visual check MUST compare the top bar and the navigation of Files at 1440 x 1000 with the
Zuiddrecht set against committed reference images, and MUST fail when more than 0.5% of the top bar's
pixels or 1% of the navigation's pixels differ by more than about 24 of 255.

#### Scenario: The shell has not moved
@e2e tests/e2e/visual/shell.visual.spec.ts
- GIVEN the Zuiddrecht set
- WHEN Files is opened at 1440 x 1000
- THEN the top bar and the navigation MUST match their reference images within their limits
