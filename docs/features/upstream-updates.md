---
sidebar_position: 28
---

# Keep token sets up to date

Municipal house styles change upstream, in the NL Design System themes repository. Thematiq picks those changes up in two ways. A nightly job in our own repository proposes updates as releases. An optional daily check on your server tells you that upstream has moved.

## How updates reach a release

A GitHub Actions workflow in the Thematiq repository runs every night. It reads the upstream `nl-design-system/themes` repository, regenerates the token CSS and opens a pull request when something changed. Reviewed changes ship in the next Thematiq release.

Your server installs those updates when you update the app. Nothing on your server rewrites a token set by itself.

## Turn on the daily check

The check is off by default, because it is the one place Thematiq contacts another server.

1. In the Thematiq admin settings, find **Upstream token updates**.
2. Tick **Check daily for upstream token updates (contacts api.github.com)**.

Once a day, a background job makes one request to `api.github.com`, and a second one only when upstream has moved. The request carries no instance URL, version or telemetry. Each request times out after 10 seconds and uses your Nextcloud proxy settings.

If your server cannot reach GitHub, point the check at an internal mirror:

```bash
occ config:app:set thematiq upstream_manifest_url --value=https://mirror.example.nl/repos/nl-design-system/themes/commits/main
```

A failed check writes one info line to the log and changes nothing else.

## What the check can tell you today

The check compares the upstream revision with the revision recorded on each installed token set. Today no shipped token set records that revision yet, so the check has nothing to compare and shows no notice. It records when it last ran. Custom token sets are never compared.

When a set does record its revision, a notice names the set and the new upstream version, with a **Dismiss** button. A dismissed notice comes back when a newer version appears. The check never applies an update: you take updates through an app release.

Next, see which sets ship and where their tokens come from in [token sets](./token-sets.md).
