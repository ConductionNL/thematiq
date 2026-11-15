# paragraph-token Delta

**Status**: proposed
**Scope**: thematiq
**OpenSpec changes**:

- [paragraph-token-yields-in-board-look](../../)

## Purpose

The paragraph token styles body text without overriding the board look's component type.

## ADDED Requirements

### Requirement: The paragraph token is forced outside the board look and a default inside it

The nldesign theme SHALL force the paragraph token on paragraphs outside the nextcloud-vue board look, and inside it SHALL
apply the token only as a default that any component rule overrides.

#### Scenario: A board-look note

- **WHEN** a board-look component draws a 13px note as a `<p>`
- **THEN** it renders at 13px, and a `<p>` without a size of its own renders at the paragraph token
