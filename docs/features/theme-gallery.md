---
sidebar_position: 19
---

# Install a house style from the gallery

The theme gallery lists house styles that municipalities, provinces and suppliers built. Install one and it becomes a custom token set, ready to choose in the Design token set list. Nobody has to convert a theme file by hand.

## Turn the gallery on

The gallery is off on a new installation, and the app contacts nobody for it. Open Settings > Administration > Theming, find **Theme gallery** and tick the toggle. Its label names the host the gallery index comes from. From then on the page reads the index while you have it open, at most once an hour, with a 10 second limit and without cookies or anything that identifies your instance.

Each house style shows its colours, the organisation behind it, its licence, a link to its source and the result of the contrast audit. An entry without a licence is not listed.

## Install a house style

Choose **Install** next to it. The app downloads the file and compares its checksum with the index. It then treats the file exactly like an upload under Custom token sets: the same conversion, the same checks, the same storage. A file that does not match the index, or that the checks refuse, installs nothing, and the block says why.

The installed set appears in the Design token set list and under Custom token sets, where a link names its source and licence. Installing does not switch your theme; you choose the set yourself.

## Updates

When the index lists a new version of a house style you installed, it shows **Update available**. Nothing changes until you choose **Update**. An update goes through the same checks as an install, keeps the set's name, and keeps it active if it was.

## Mirror the gallery

An organisation behind an egress filter can host its own copy. Copy `gallery/index.json` and the files it lists to an internal web server, point each `fileUrl` in the copy at the internal files, and set the index URL:

```bash
occ config:app:set thematiq gallery_index_url --value="https://intranet.example.nl/thematiq/index.json"
```

The toggle label then names `intranet.example.nl`. To go back to the public index, delete the setting:

```bash
occ config:app:delete thematiq gallery_index_url
```

## Add your house style

The public index lives in this repository under `gallery/`. `gallery/CONTRIBUTING.md` explains the entry format and what is checked before an entry is merged.

## Outbound requests

The app makes two kinds of outbound request, and both are off by default: the daily upstream token check (api.github.com) and the theme gallery (the host its toggle names).
