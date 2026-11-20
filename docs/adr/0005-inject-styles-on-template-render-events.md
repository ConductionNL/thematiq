# 0005. Styles are injected on template render events, not at app boot

- Status: Accepted

## Context

Theme stylesheets used to be added from `Application::boot()`. That ran config reads, service resolution and a filesystem check on every request, including WebDAV, OCS, API, cron and preview requests that render no page.

## Decision

- Stylesheets are added from a lazy listener on `BeforeTemplateRenderedEvent` and `BeforeLoginTemplateRenderedEvent`, the same events Nextcloud core uses for its own theme injection.
- The app a page belongs to is taken from the response's app id first, with path matching only as a fallback; an unresolved id fails open to themed.

## Consequences

- Requests that render no template no longer pay for theming. The listener is `lib/Listener/ThemeInjectionListener.php`, registered in `lib/AppInfo/Application.php`.
- Every surface that core theming reaches is themed; anything rendered outside these events was not themed at boot either.

## Source

`openspec/changes/archive/2026-07-23-render-event-injection/design.md` (sections 1 and 5).
