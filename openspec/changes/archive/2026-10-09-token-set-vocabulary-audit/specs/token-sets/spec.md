# Spec delta: Token Sets (token-set-vocabulary-audit)

Adds the mechanical definition of a *correct* shipped token set — one that declares the
`--nldesign-*` vocabulary its design system actually reads — and the admin surface that says so when
a set does not. The existing "Token Set CSS Structure" requirement stays true exactly as written
(a set MAY override any `--nldesign-*` variable, and an incomplete set MUST still render); this delta
adds the separate statement that a *shipped* set MUST NOT rely on that fallback for the tokens that
carry a brand's identity.


## Status: LANDED

This delta has no ADDED Requirements section any more, because it has none left to add. Both
requirements now live in `openspec/specs/token-sets/spec.md`, copied there verbatim by `8e57d62` /
`f80c126` so the `@spec` anchors
`#requirement-shipped-token-set-vocabulary-completeness` and
`#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown` resolve against the
source-of-truth spec rather than against an unmerged change.

The text is deliberately not repeated here. Two live normative copies of one requirement drift
apart, and the canonical spec is the one every `@spec` tag points at.

- `### Requirement: Shipped Token Set Vocabulary Completeness` — the three mechanical rules, the
  26 required semantic tokens, and the allow-list's shrink-only contract.
- `### Requirement: Incomplete Sets Are Surfaced In The Admin Dropdown` — the badge, the tooltip,
  and the reuse of the existing `warnings` channel.

**Not archived yet.** `tasks.md` still carries three unchecked items — 7.4 (`composer check:strict`
over the changed PHP), 7.5 (the full PHPUnit suite) and 7.6 (a Playwright spec for the "Incomplete
set" badge and tooltip). 7.4 and 7.5 have since been run green in CI, so 7.6 is the only one with
work left in it; tick the other two and archive this change once that spec exists. Nothing further
needs to land in the spec by then.
