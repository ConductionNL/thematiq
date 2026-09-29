# Spec delta: compliance evidence (compliance-evidence)

The report the endpoint already serves becomes reachable from the admin settings page.

## ADDED Requirements

### Requirement: Download From the Admin Panel

The admin settings page MUST offer the compliance evidence report as two download links, one for JSON and one for Markdown, in a section of its own. Each link MUST point at the Admin Export Endpoint with its `format` parameter, so the browser saves the file the endpoint names. The section MUST state that the report covers the colour contrast of the theme tokens only and is not a full WCAG audit.

#### Scenario: An administrator downloads the contrast evidence report from the settings page

- GIVEN an administrator on the Theming settings page with a token set active
- WHEN they look at the "Contrast evidence report" section
- THEN they MUST see a "Download as JSON" link and a "Download as Markdown" link
- AND following the JSON link MUST return the report with `Content-Disposition: attachment`
