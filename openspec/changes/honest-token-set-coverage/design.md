# Design: honest token-set coverage

## The problem in one sentence

The repo's own audit said every shipped set was complete, because the only thing it measured
was the 26 semantic tokens, and "complete by that rule" reads as "this instance looks like
this organisation" to anyone who does not open the script.

This is the shape my memory calls *the instrument answers about something adjacent*. The fix
is not a better number; it is measuring the four things an administrator actually sees, and
letting each one fail.

## D1. Four dimensions, and the bar for each

| dimension | what it measures | bar | why that bar |
|---|---|---|---|
| `bridge` | of the 87 `--utrecht-*` names `css/systems/nldesign/utrecht-bridge.css` reads, how many the set declares | `> 0` — **withdrawn**, see below | A set at zero was assumed to dress every component in Rijkshuisstijl. **That assumption was wrong.** The follow-up change `every-shipped-set-selectable` resolved the bridge's own fallbacks and found that 42 of its 84 declarations fall back to a `--nldesign-*` token the set declares, 38 to a non-colour literal and 3 to a colour literal, so a bridge-zero set brands its component colours from its own semantic layer. The dimension is now measured and reported, never gated. |
| `font` | whether the first family the set names has an `@font-face` in a stylesheet its own design system **links** | served, a system family, or declared undistributable | A face declared in a stylesheet nothing links never loads, which is exactly how Figtree looked correct in the repository and rendered Arial on an instance. |
| `logo` | whether the set points Nextcloud theming at a logo | present | Otherwise the Nextcloud logo stays in a municipality's header. |
| `contrast` | the verdict in `docs/reference/contrast-report.md` | `pass` | `unevaluated` is not a pass, and two of the three reasons a set was unevaluated were fixable. |

The 87 is read out of the bridge, not typed in: counting it with comments included gives 88,
because the file's own documentation writes `var(--utrecht-Y, <fallback>)` as prose.

## D2. Where each dimension is enforced, and why not all in one place

A fact is gated once, in the runtime that owns it. Two implementations of one rule are two
answers waiting to disagree.

- `bridge`, `logo`: file facts, no colour maths, no runtime need. Enforced in
  `tests/vitest/tokenSetCoverage.spec.js` against the same functions the CLI runs (the script
  exports them; `main()` runs only when the file is the command).
- `font`: needs a PHP implementation anyway, because the admin UI shows the verdict at
  runtime. `TokenSetFontAuditService` + `tests/Unit/Service/TokenSetFontAuditTest.php`. The
  Node side classifies the same way and one vitest assertion compares the two
  `SYSTEM_FAMILIES` lists so they cannot drift.
- `contrast`: relative luminance, colour parsing, `color-mix()`. One engine
  (`ShippedTokenSetAuditService`), which now writes `docs/reference/contrast-report.json`
  beside the markdown so the Node table reads a verdict rather than recomputing one.
  `tests/Unit/TokenSetContrastAuditTest.php` holds both files to the same staleness check.

## D3. The ratchet has to be able to fail three ways

The previous allow-list was an array of ids. An array of ids cannot say why, and the empty
array said nothing at all. The new fixture is `{dimension: {setId: reason}}` and the gate
fails on:

1. a set below the bar that is not listed — a regression;
2. a listed set that now passes — progress that must be recorded by deleting the entry;
3. an entry whose reason is under 20 characters — because an allow-list without reasons is how
   a baseline becomes permanent.

Each of the three is proven by a probe in the spec that moves the real data and shows the rule
noticing. Removing one `logo` entry by hand was also run: the CLI exits 1 and three
assertions fail.

## D4. Which typefaces are shipped, and which are refused

Shipped, licence read from the package's own `LICENSE` rather than from the registry's
`license` field:

| family | SPDX | sets it serves |
|---|---|---|
| Open Sans | OFL-1.1 | bodegraven-reeuwijk, leiden, leidschendam-voorburg |
| Lato | OFL-1.1 | demodam, dinkelland, ridderkerk, tubbergen |
| Roboto | OFL-1.1 | buren, noordoostpolder, noordwijk |
| Source Sans Pro | OFL-1.1 | duiven, westervoort, zevenaar |
| IBM Plex Sans | OFL-1.1 | enschede, losser |
| Clear Sans | Apache-2.0 | hoorn |
| Figtree | OFL-1.1 | conduction-new (bytes already in the tree, wired into the linked layer) |

Weights 400/600/700 normal where upstream has them; Lato and Clear Sans ship 400/700 only, so
600 is left to the browser to synthesise.

Refused, and the reason is the same for all of them: no release exists under a licence that
permits redistribution in an app package. Checked mechanically on 2026-10-04 — none of
`@fontsource/{din,din-next,bolder,avenir,calibri,droid-sans,karbon,rijksoverheidsans,sofia-pro,greycliff-cf,neue-haas-grotesk,marianne}`
exists on the npm registry, while `@fontsource/clear-sans` does.

`Droid Sans W01` (borne) deserves its own sentence: `W01` is a Monotype web-font edition. The
retired Apache-2.0 Droid Sans is a different file, and shipping it under this name would
misstate what it is, so it is refused rather than substituted.

Marianne (cunningham, lasuite) is not a new problem and gets no new mechanism: it already
ships behind the admin acknowledgement gate (`css/systems/lasuite/marianne.css`,
`AGREEMENT-MARIANNE.md`) and resolves through `local()` otherwise. Its `font` block records
`action: acknowledge` so the admin banner asks for the acknowledgement, not an upload.

Arial (drechterland, enkhuizen, stedebroec, tilburg) is **not** a defect and is not warned
about. It is what those house styles specify, the operating system supplies it, and it cannot
be self-hosted.

## D5. The logo question is a licensing question, and it stops at the mechanism

34 selectable sets carry no logo. Shipping one is not blocked by anything technical:
`theming.logo` already exists, 22 sets use it, and `img/logos/` holds 36 files.

What blocks it is that an organisation's wordmark is its own trademark. The repository has
already met this and already decided: `img/logos/dinkelland.svg`, `noaberkracht.svg` and
`tubbergen.svg` exist and are wired to **no set**, because they are grey placeholder
rectangles carrying the comment *"replace with official asset when redistribution is
permitted"* (commit `0e5a732b`). Wiring them up would present a grey rectangle as a
municipality's logo, which is worse than showing the Nextcloud one, so this change leaves
them exactly as they are.

For the other 31 the upstream was checked: 30 record `nl-design-system/themes` commit
`9cfc4c5a`, which ships design tokens and no logo asset; `zwolle` records
`@nl-design-system-unstable/zwolle-design-tokens`, likewise tokens only. Four sets
(`conduction-new`, `cunningham`, `hoog-contrast`, `lasuite`) are design-system showcases
rather than organisations, so there is no wordmark to ship and the Nextcloud logo is the
correct default.

So the mechanism is what this change delivers: the audit reports the gap per set, the gate
fails on a new set that ships without a logo, and each of the 34 reasons names what is
actually known about that set. The upload path an administrator uses already exists. No logo
is invented, traced or vendored.

## D6. Why `vng` passes without a brand colour moving

`vng` declares `--nldesign-color-primary: #003865` and `theming.background_color: #0277BD`.
The audit measured the primary against the manifest background and got 2.50:1.

`#0277BD` is **Nextcloud's login background**, which the repository already says in
`ShippedTokenSetAuditService::portalCascade()`: *"NOT theming.background_color, which
resolveDeclarations() falls back to: that is Nextcloud's login background (vng's is #0277BD),
and a portal never paints its page with it."* vng's own page is white — the set declares
`--utrecht-document-background-color: var(--tilburg-color-white)`.

So the fix is to measure the right surface, not to loosen the threshold, which stays 3.0:1:
the set now declares `--nldesign-color-background: #ffffff`, its own document background, and
#003865 on white is 11.98:1. `theming.background_color` is untouched, so the login page looks
exactly as it did.

Two further corrections, both measured: the research note said all seven `unevaluated` sets
were unevaluated "because the value is a `var()`". Only **one** was (`conduction-new`, a
two-hop chain). The other six — `enschede`, `hoeksche-waard`, `losser`, `nora`, `purmerend`,
`zaanstad` — declare no `--nldesign-color-background` **and** no
`theming.background_color`, so the pair had no second colour at all. The background chain
gains a `#ffffff` terminus, which is the page `css/public-bridge.css`
(`var(--nldesign-color-background, #fff)`),
`scripts/mapping/nlds-to-nextcloud.json` (`"page": "#ffffff"`) and `ComplianceReportService`
already assume. Only the terminus is new; the four sets with a genuinely pale page
(`rijkshuisstijl` #F5F6F7, `noordwijk` #f2f7fa, `summer-breeze` #EAF2FB, `cunningham`
#E1E2E5) keep their own value and their ratios are unchanged to two decimals.

## D7. The generator that would have deleted a font

`scripts/build-fonts.js` held one hard-coded Fira Sans stylesheet string and wrote it over
`css/fonts.css`. Source Sans 3 was added to that file by hand for the (EXAMPLE) Gemeente set,
so `npm run build` would have silently deleted it. And `css/systems/nldesign/fonts.css` — the
file the nldesign design system actually links, so the only one that matters on an instance —
was written by no generator at all and kept identical by hand.

It is now one `FAMILIES` table generating both stylesheets and both licence notices, with a
`--check` mode (`npm run test:fonts`) that fails on a hand edit, a missing binary, a notice
that does not name a bundled family, and a font binary no table claims. That last rule is how
a real hole was found: both `OFL.txt` notices had omitted Source Sans 3 since it was added,
and `tests/vitest/fontLicences.spec.js` had its own hand-typed list of four families that did
not name it either. A licence gate that only checks the families someone remembered to list is
the gate not running. That spec now derives its families from the generator's tables.

## D8. Measured baseline, 2026-10-04 (`origin/development` 3dc02602 + this change)

| | before | after |
|---|---|---|
| sets passing all four dimensions | not measured (stage 2 did not exist) | 17 of 56 |
| bridge at zero (of bridged sets) | 8 of 52 | 8 of 52, allow-listed with a reason — withdrawn by the follow-up change, which demotes the dimension to reported-only |
| font served (of the 58 manifest sets) | 19 | 36 |
| font named but unserved and undeclared | 33 | 0 |
| | | |
| logo absent | 34 of 56 | 34 of 56, each allow-listed with a reason |
| contrast pass / fail / unevaluated | 49 / 1 / 7 (of 57) | 57 / 0 / 0 |
| stage-1 vocabulary allow-list | 0 entries, no reasons | unchanged, still 0 |
| stage-2 coverage allow-list | did not exist | 42 entries, every one with a reason |

The two font rows count the 58 entries in `token-sets.json`; the coverage table's own font
column counts the 56 SELECTABLE sets whose design system reads the `--nldesign-*` vocabulary,
so it reads 36 self-hosted as well but over a different denominator. Both numbers are the same
measurement; the denominator is the thing to read.

Regenerate the whole table with `node scripts/audit-token-sets.mjs --json` and
`npm run audit:token-sets:report`.

Per-set bridge figures, highest first: opencatalogi and rotterdam 79; the five example sets
and vng 74; nijmegen 58; enschede, leiden, losser, zaanstad 55; dinkelland, hoeksche-waard,
noaberkracht, tubbergen 53; noordwijk 52; xxllnc 46; haarlem 27; conduction-new 26; tilburg
25; provincie-zuid-holland, venray, vught 23; demodam, purmerend, stedebroec 21;
leidschendam-voorburg, noordoostpolder 20; drechterland, enkhuizen, hoorn 19;
bodegraven-reeuwijk, nora 18; buren 17; haarlemmermeer 16; duo 15; duiven, horstaandemaas,
riddeliemers 10; borne, westervoort, zevenaar 8; amsterdam, denhaag, epe, groningen,
ridderkerk, rijkshuisstijl, utrecht, zwolle 0. `cunningham`, `frankendesk`, `hoog-contrast`
and `lasuite` are `n/a`: their design systems do not link the Utrecht bridge.
