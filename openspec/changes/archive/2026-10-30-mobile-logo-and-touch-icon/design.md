# Design: mobile-logo-and-touch-icon

Kept apart from the token sets: these are workspace images, the same whichever set is active, like
Nextcloud's own logo and favicon. They live in app data `workspace-icons/`, checked by the same
`ImageSniffer` as the logos of a brand per app.

The header rule sits inside `@media (max-width:1024px)` with the selector `#header #nextcloud .logo`.
That outranks the design systems' own header-logo rules and Nextcloud's, and yields to a brand per
app, whose rule starts with `body`. The address is filtered to URL characters before it is written
into the rule.

`WorkspaceIconListener` runs on `BeforeTemplateRenderedEvent` and `BeforeLoginTemplateRenderedEvent`,
apart from the theme listener, so the images reach a page even when theming is switched off for
that app. It fails open and logs. Our link carries `sizes="180x180"`, which iOS prefers over
Nextcloud's unsized one.
