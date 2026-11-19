# The mark colour per token set

Nextcloud paints `<mark>`, the highlight on a search hit, with `--color-mark` behind the inherited body text
(`--color-main-text`). It does not use a set's `--utrecht-mark-color`. A set's `--utrecht-mark-background-color`
becomes the mark colour only when body text keeps 4.5:1 on it in the light theme and in both dark scopes
(Q-thematiq-12, decided 19 Nov 2026). Every other set keeps Nextcloud's own tint, `#fff0c7` in the light and
`#4d3800` in the dark.

Measured 19 Nov 2026 on the shipped sets that declare a mark background, body text on that background. The dark
column holds for both dark scopes (a system dark preference and a chosen dark theme), which measure the same.

| Set | Light | Dark | Mark colour |
| --- | --- | --- | --- |
| hoeksche-waard | #0A2750 on #FFF5BE, 13.43:1 | #afccf5 on #5b4d05, 5.09:1 | the set's (`css/token-overrides/hoeksche-waard.css`) |
| purmerend | #000000 on #f0ad4e, 10.79:1 | #ebebeb on #b27218, 3.32:1 | Nextcloud's: the dark pair is under 4.5:1 |
| groningen | #0f0c0b on #695817, 2.79:1 | #eee9e7 on #e0ce8a, 1.31:1 | Nextcloud's: both pairs are under 4.5:1 |
| leiden | #0D1921 on #695817, 2.55:1 | #deeaf2 on #e0ce8a, 1.28:1 | Nextcloud's: both pairs are under 4.5:1 |
| rotterdam | #404B4F on #695817, 1.29:1 | #b0bbbf on #e0ce8a, 1.25:1 | Nextcloud's: both pairs are under 4.5:1 |
| enschede | #1B1B1B on #695817, 2.47:1 | #e4e4e4 on #e0ce8a, 1.24:1 | Nextcloud's: both pairs are under 4.5:1 |
| losser | #060606 on #695817, 2.90:1 | #ebebeb on #e0ce8a, 1.32:1 | Nextcloud's: both pairs are under 4.5:1 |
| zaanstad | #333333 on #695817, 1.81:1 | #cccccc on #e0ce8a, 1.02:1 | Nextcloud's: both pairs are under 4.5:1 |
| voorne-aan-zee | #1B1B1B on #695817, 2.47:1 | #e4e4e4 on #e0ce8a, 1.24:1 | Nextcloud's: both pairs are under 4.5:1 |
| sittard-geleen | #050708 on #555555, 2.71:1 | #e6ecef on #a3a3a3, 2.12:1 | Nextcloud's: both pairs are under 4.5:1 |
| vidar | #050708 on #555555, 2.71:1 | #e6ecef on #a3a3a3, 2.12:1 | Nextcloud's: both pairs are under 4.5:1 |

The decision named seven sets; purmerend, voorne-aan-zee, sittard-geleen and vidar have declared a mark background
since, and measure the same way. `tests/vitest/markColourMapping.spec.js` holds the rule: exactly the sets in its
`MAPPED` list follow their mark colour, each passes 4.5:1 in every mode, and every other set falls under it in at
least one. A set that starts to pass after an upstream change fails that test until it is added to the list and given
its override file. An administrator's own mark colour (`--nldesign-nc-color-mark` in the editor) still wins.
