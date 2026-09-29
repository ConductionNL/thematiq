# Spec delta: theme gallery (theme-gallery)

A new capability. An administrator browses an index of house styles that others built and installs one.

## ADDED Requirements

### Requirement: The gallery is opt-in and disclosed

The gallery MUST default to off (`gallery_enabled`). Its toggle on Settings > Administration > Theming MUST name the host of the index URL. While off, the app MUST make no gallery request. The index URL MUST be configurable through app config `gallery_index_url` so a deployment behind an egress filter can use a mirror. Gallery requests MUST NOT carry instance-identifying data and MUST time out after 10 seconds.

#### Scenario: A fresh install contacts nobody

- GIVEN a new installation where no administrator has touched the gallery toggle
- WHEN an administrator opens Settings > Administration > Theming
- THEN the Gallery block MUST show the off toggle with the index host in its label
- AND the server MUST make no request to that host

#### Scenario: An administrator points the gallery at a mirror

- GIVEN `gallery_index_url` set to `https://intranet.example.nl/thematiq/index.json`
- WHEN an administrator turns the gallery on
- THEN the toggle label MUST name `intranet.example.nl`
- AND the index MUST be read from that URL

### Requirement: An administrator browses the gallery

With the gallery on, the Gallery block MUST list every valid index entry with its name, organisation, swatches, licence, source link and contrast result. Entries without an SPDX licence or with a malformed field MUST NOT be listed. When the index cannot be reached, the block MUST say so and point to installing by upload.

#### Scenario: An administrator finds their province

- GIVEN the gallery is on and the index lists `provincie-utrecht`
- WHEN an administrator opens the Gallery block
- THEN they MUST see "Provincie Utrecht" with its swatches, licence and contrast result

#### Scenario: The index is unreachable

- GIVEN the gallery is on and the index host does not answer within 10 seconds
- WHEN an administrator opens the Gallery block
- THEN the block MUST state that the gallery could not be reached
- AND it MUST offer the custom token set upload instead

### Requirement: Installing is checked and goes through the upload path

Installing an entry MUST download its file, compare the SHA-256 with the index, and on a match store it through the same conversion, CSS validation whitelist and `CustomTokenSetService::store()` as an upload. A mismatch or a whitelist refusal MUST store nothing and say why. The installed set MUST carry provenance (gallery id, source URL, licence, checksum, install date), shown in the custom token sets list.

#### Scenario: An administrator installs a house style from the gallery

- GIVEN the gallery lists `provincie-utrecht` with a correct checksum
- WHEN an administrator chooses install
- THEN the Design token set dropdown MUST offer the installed set
- AND the custom token sets list MUST show its source and licence

#### Scenario: A tampered file is refused

- GIVEN an index entry whose file no longer matches its checksum
- WHEN an administrator chooses install
- THEN nothing MUST be stored
- AND the block MUST state that the file did not match the index

### Requirement: Updates are offered, never applied

When the index lists a different checksum for an installed gallery set, the block MUST show that an update is available. The app MUST NOT download or apply it until an administrator chooses update, and an update MUST go through the same checks as an install.

#### Scenario: A supplier publishes a corrected set

- GIVEN an installed gallery set and a newer entry for it in the index
- WHEN an administrator opens the Gallery block
- THEN the set MUST show "update available"
- AND the active token set MUST be unchanged until they choose update

### Requirement: Gallery endpoints are admin-only

`GET /settings/gallery` and `POST /settings/gallery/{id}/install` MUST carry `#[AuthorizedAdminSetting(OCA\Thematiq\Settings\Admin::class)]`.

#### Scenario: A non-admin cannot install

- GIVEN a signed-in user who is not an administrator
- WHEN they call `POST /apps/thematiq/settings/gallery/provincie-utrecht/install`
- THEN the response MUST be 403 and nothing MUST be stored
