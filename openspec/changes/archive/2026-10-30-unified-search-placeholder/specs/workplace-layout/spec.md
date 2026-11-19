# workplace-layout Delta

**Status**: proposed
**Scope**: thematiq
**OpenSpec changes**:

- [unified-search-placeholder](../../)

## Purpose

The top bar's search field can speak for the app it is on.

## ADDED Requirements

### Requirement: An app or the theme words the search field

Thematiq SHALL show, in the unified search field of a page of an app, the theme's text for that app, else the app's own
`search_placeholder`, else the theme's default, each translated through the owning catalogue; and SHALL leave
Nextcloud's words when none is set or a failure occurs.

#### Scenario: DqKop

- **WHEN** dossiq declares `search_placeholder` "Search cases, documents or contacts" and a Dutch user opens a dossiq page
- **THEN** the search field reads "Zoek zaken, documenten of contacten"

#### Scenario: The theme overrides an app

- **WHEN** `search_placeholders` holds an entry for dossiq
- **THEN** that entry is shown instead of dossiq's own text
