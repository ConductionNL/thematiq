---
sidebar_position: 25
---

# Download contrast evidence

An accessibility statement needs evidence. Thematiq measures the colour contrast of the theme that is live right now and gives you the result as a file.

## Download the report

In the Thematiq admin settings, find **Contrast evidence report**. Click **Download as JSON** or **Download as Markdown**.

Or run it from the command line:

```bash
occ thematiq:compliance-report --format=markdown --output=contrast.md
```

The command writes JSON to standard output by default. It exits with 0 whenever it produced a report, also when the verdict is fail. A non-zero exit means no report was made. That lets a pipeline tell "the evidence says fail" apart from "there is no evidence".

## What it measures

The report resolves every token the way the browser would: design system defaults, then the token set, then your own overrides. It then checks 18 colour pairs against WCAG 2.2 AA.

| Pairs | Threshold | Success criterion |
|---|---|---|
| 9 text pairs, such as main text on the main background | 4.5:1 | 1.4.3 |
| 9 non-text pairs, such as the primary colour or error border on the main background | 3:1 | 1.4.11 |

The report lists each pair, so an auditor sees what was measured and what was not.

## Reading the verdict

Each pair passes, fails or is `unevaluated`. A pair is unevaluated when its value is not a plain colour, for example an unresolved `var()`. An unevaluated pair never counts as a pass.

| Verdict | Meaning |
|---|---|
| `pass` | No pair failed and every pair was evaluated |
| `incomplete` | No pair failed, but at least one was unevaluated |
| `fail` | At least one pair failed |

The report also names the instance, the app and Nextcloud versions, the active token set and a hash of your overrides. The same configuration gives the same report, byte for byte.

## What it does not cover

The report covers colour contrast of the theme only. It is not a WCAG-EM audit and not a full WCAG evaluation. Content, keyboard use and semantics are outside it, and the report says so itself. Your accessibility statement still needs an expert WCAG-EM evaluation. The report is supporting evidence for it.

For the contrast of every shipped set, not only the active one, see the [shipped token-set contrast audit](./contrast-audit.md).

Next, attach the Markdown file to your accessibility statement.
