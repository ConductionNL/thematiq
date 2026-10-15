# Spec delta: mobile logo and touch icon (mobile-logo-and-touch-icon)

## ADDED Requirements

### Requirement: An administrator sets a mobile logo and a touch icon

An administrator MUST be able to upload, replace and remove a mobile logo (PNG, JPEG, WebP or an SVG
without script, at most 1 MB) and a home screen icon (a square PNG of at least 120 by 120 pixels, at
most 1 MB). Any other file MUST be refused with the reason. Only an administrator MUST be able to
change them.

#### Scenario: A touch icon that is not square
@e2e exclude a file upload refused on its pixel size, checked on the bytes; proven by tests/Unit/Service/WorkspaceIconServiceTest.php (testAnUnusableImageIsRefused, testTheControllerUploadsServesAndRemoves, testWhoMayDoWhat)
- WHEN an administrator uploads a PNG of 180 by 120 pixels as the home screen icon
- THEN it MUST be refused with "Upload a square PNG of at least 120 by 120 pixels; 180 by 180 is best."

### Requirement: The mobile logo and the touch icon reach every page

When a touch icon is set, every page and the login page MUST link it as `apple-touch-icon`. When a
mobile logo is set, the header MUST draw it below 1024 px and the logo MUST be unchanged above that
width. With neither set, nothing MUST be added. Both images MUST be readable without a session. An
image that cannot be read MUST NOT break a page.

#### Scenario: Both images are set
@e2e exclude the head tags are captured from the listener; a browser run needs uploaded images on a live instance, which this change does not have; proven by tests/Unit/Service/WorkspaceIconServiceTest.php (testTheListenerAddsATagPerImage, testTheMobileLogoRuleStaysInsideItsMediaQuery, testTheListenerFailsOpen)
- GIVEN a touch icon and a mobile logo
- WHEN the login page is drawn
- THEN its head MUST link the touch icon with `sizes="180x180"`
- AND carry a rule for the header logo inside `@media (max-width:1024px)`
