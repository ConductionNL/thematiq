# Design: theme gallery

## Where it fits (development b4e7568)

- `lib/Service/UpstreamFreshnessService.php` is the app's one outbound request: opt-in (`upstream_freshness_enabled`, `:55`, default off), a pinned and overridable URL (`upstream_manifest_url`, `:62`, default `:114`), `IClientService` (`:25`), bounded requests and silent degradation. `openspec/specs/upstream-freshness/spec.md:45-56` makes that normative, and the settings hint at `templates/settings/admin.php:532-535` says "This is the only outbound network request this app makes".
- `lib/Controller/CustomTokenSetController.php:203` `upload()` converts the input and hands it to `lib/Service/CustomTokenSetService.php:191` `store()`, which refuses a duplicate id (`409`) and writes `css/tokens/custom-<slug>.css` plus a manifest entry. The CSS validation whitelist (`openspec/specs/custom-token-sets/spec.md:42`) is what makes an uploaded set safe to serve on the login page.
- The upstream-freshness design (`openspec/changes/archive/2026-07-23-upstream-token-freshness/design.md` section 6) records no auto-apply and no auto-download as non-goals. The gallery keeps both: every download follows an administrator's click.

## Decisions

### 1. The index is a JSON file at a URL

`gallery_index_url` (app config) defaults to the `gallery/index.json` file on this repository's `development` branch, served raw from GitHub. Each entry: `id`, `name`, `organisation`, `description`, `licence` (SPDX), `sourceUrl`, `fileUrl`, `sha256`, `format` (`css`, `dtcg`, `design-tokens-css`), `swatches` (primary, background, text), `contrast` (the result of `npm run audit:token-sets` at the time it was added), `addedOn`. An organisation behind an egress filter mirrors the index and the files and points the URL inward.

Rejected: the Nextcloud app store. It distributes apps, not data files, and one app per house style would be absurd.

Rejected: fetching NL Design System theme packages straight from npm. Package contents vary per organisation; the index is where a human checks that a file converts cleanly before others install it.

### 2. Opt-in, disclosed, bounded

`gallery_enabled` defaults to off. The toggle label names the index host. While off, the app makes no gallery request. When on, the index is fetched when the administrator opens the block (at most once per hour, cached with its ETag), never from a background job. Requests carry no instance-identifying data and time out after 10 seconds.

### 3. Install equals upload, plus a checksum

Install downloads `fileUrl`, compares its SHA-256 with the index, and on a match runs exactly the upload path (`TokenSetConverterService`, the whitelist, `CustomTokenSetService::store()`). A mismatch installs nothing. The stored manifest entry gains `provenance: {galleryId, sourceUrl, licence, sha256, installedOn}`, shown in the custom sets list.

### 4. Updates are offered, never applied

When the index lists a newer `sha256` for an installed `galleryId`, the block shows "update available". Updating is an explicit click and replaces the set through the same path, with an audit entry `custom_set_uploaded` carrying the gallery id.

### 5. Contribution through a pull request

`gallery/CONTRIBUTING.md` explains the entry format and that the file must pass the converter and the contrast audit. The index is reviewed like code; the licence must allow redistribution.

## Risks

- A malicious file in a mirror: the checksum is checked against the index from the same mirror, so the defence is the upload whitelist, which already treats every file as untrusted.
- Licences: an entry without an SPDX licence is not listed.

## Out of scope

- Paid themes, ratings, reviews.
- Auto-updating installed sets.
