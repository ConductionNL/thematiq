# Spec delta: Token Sets (token-set-vocabulary-audit)

Adds the mechanical definition of a *correct* shipped token set — one that declares the
`--nldesign-*` vocabulary its design system actually reads — and the admin surface that says so when
a set does not. The existing "Token Set CSS Structure" requirement stays true exactly as written
(a set MAY override any `--nldesign-*` variable, and an incomplete set MUST still render); this delta
adds the separate statement that a *shipped* set MUST NOT rely on that fallback for the tokens that
carry a brand's identity.


## ADDED Requirements

> **LANDED — this delta's requirements now live in `openspec/specs/token-sets/spec.md`.**
>
> They were copied there verbatim (commits `8e57d62` / `f80c126`) so the `@spec` anchors
> `#requirement-shipped-token-set-vocabulary-completeness` and
> `#requirement-incomplete-sets-are-surfaced-in-the-admin-dropdown` resolve against the
> source-of-truth spec rather than against an unmerged change. The full text is deliberately NOT
> repeated here: two live normative copies of one requirement drift apart, and the canonical spec is
> the one every `@spec` tag points at.
>
> This change stays out of `changes/archive/` because it is not finished — task 7.6 (a Playwright
> spec for the "Incomplete set" badge and tooltip) is still open. Archive it once that closes; there
> is nothing left to land in the spec by then.
>
> - `### Requirement: Shipped Token Set Vocabulary Completeness` — the three mechanical rules, the
>   26 required semantic tokens, and the allow-list's shrink-only contract.
> - `### Requirement: Incomplete Sets Are Surfaced In The Admin Dropdown` — the badge, the tooltip,
>   and the reuse of the existing `warnings` channel.
