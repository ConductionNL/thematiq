---
kind: code
---

# Proposal: platform-properties

## Summary

Nine capability rows describe something Nextcloud server already does, and thematiq only lives next to it: logo, background and favicon upload, the custom domain, the legal footer links, the product name, the personal theme choice, admin delegation and the app store install. All nine are built, but no requirement says how thematiq relates to them, so nothing can cite them and nothing stops a later change from breaking the way thematiq composes with core. This change writes them down as a `platform-properties` spec: per property, who provides it and what thematiq SHALL and SHALL NOT do with it.

## The rows this covers

Source: thematiq `openspec/parity/capabilities.json`. All are `built`, owner `nextcloud/server`.

- **ast-upload-logo** Upload a logo of your own.
- **ast-upload-background** Upload a login background image of your own.
- **ast-favicon** Set the browser tab icon (favicon) to your organisation's.
- **sur-custom-domain** Serve the branded workspace on the organisation's own domain name.
- **sur-footer-links** Add legal links (privacy statement, imprint, accessibility statement) to the footer and login page.
- **sur-web-app-name** Change the product name, slogan and web address shown to users.
- **sco-user-choice** Let each user choose a theme (light, dark, high contrast) for themselves.
- **gov-delegated-role** Delegate theme editing to a brand manager who is not a full administrator.
- **gov-app-store-install** Install and update the theming from the platform's app store.

## What changes

1. A new capability `platform-properties` with one requirement per property (REQ-TPLAT-001 to 009).
2. `@spec` tags on the code that composes with each core property.
3. Unit tests that pin the composition points: the uploaded core logo wins over the set's logo, the delegated settings list, and the image keys the theming sync hands to core.
4. `openspec/parity/capabilities.json`: the nine rows cite this change and their requirement instead of `spec: none`.

## Screens

None of thematiq's own, except the favicon: the theming sync dialog (TqThemingBijwerken) lists the favicon among the images it hands to core. See `screens.md`.

## Out of scope

- Changing core's Theming page. It belongs to nextcloud/server.
