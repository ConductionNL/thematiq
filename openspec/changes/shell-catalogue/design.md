# Design: export every shipped token set as shell roles

## Decision 1: the roles are the endpoint's roles

`ShellCatalogueService::build()` calls `ShippedTokenSetAuditService::resolveDeclarations()` and
`ShellRoleMapper::map()` per set, the two calls `ShellTokenService` makes. It leaves out what only
an instance has: an administrator's values, the stock set's live values, an uploaded logo.

## Decision 2: no version and no date in the committed file

`generated_from` is `sets:` plus sixteen hex characters of the SHA-256 of the sets and fonts. The
brief asked for a version or a commit. Thematiq's version moves on every release and a commit
cannot name itself, so either would make the committed file stale without a set changing. A copy
made for another product may name its source: `--from "thematiq <commit>"` puts that in front.

## Decision 3: redistributable is read, never assumed

`REUSE.toml` gives every file nobody claimed the repository default, EUPL-1.2 by Conduction. For a
municipality's logo that is a label, not a statement: nobody wrote down whose artwork it is. So the
default alone never makes a logo redistributable.

| Answer | When | Logos today |
|---|---|---|
| `true` | `token-sets.json` describes the set as made up or as Conduction's own brand, or the file says it is a made-up logo or our stand-in for an organisation's own | 14 sets, 20 files |
| `false` | The repository quotes a prohibition. `docs/reference/assets.md` does for the Rijksoverheid mark | 1 set |
| `null` | A logo file with nothing but the default | 15 sets |

A set without a logo file has no answer to give (34 sets). A dark logo stands with the light one
when the reason holds for the whole set, or when the dark file says it was derived from the light
one.

Each answer carries its `basis`: the sentence or the file it rests on. A statement cited from a
document is only used while the quoted words are still in that document.

## Decision 4: fonts

The font list holds every family a set's `font-family` role names, apart from the system fonts
`TokenSetFontAuditService` already lists.

- `true`: thematiq ships files for it, `REUSE.toml` names those files under OFL-1.1 or Apache-2.0,
  and the notice that must travel with them is listed in `licence_files`.
- `false`: the repository says no. Marianne ships under the Etalab licence and
  `MARIANNE-LICENCE.md` reserves it for French State administrations. A family a set's `font` block
  calls proprietary or restricted is not shipped at all.
- `null`: a family named in a fallback stack that thematiq neither ships nor describes.

## Not in this change

- No dark roles. A later change adds them from the dark palette derivation.
- No statement on the 15 logos marked `null`. That needs a person to find out and write it down,
  in `REUSE.toml` or beside the file. The catalogue picks it up on the next run.
