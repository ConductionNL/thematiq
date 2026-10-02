# NL Design System — Newman API-contract suite

`thematiq.postman_collection.json` locks the HTTP contract of nldesign's
admin-theming controllers (`appinfo/routes.php` +
`lib/Controller/SettingsController.php` + `lib/Controller/OverridesController.php`):
token-set selection + preview, theming values, the login-page slogan/menu-label
toggles, and the custom CSS-token overrides (read / write / export / import).

nldesign is a server-rendered admin theming app (its UI is `admin.js` injected
into NC's settings framework), but it has a small, real JSON API — this suite
exercises it end to end.

## Running

```bash
./run-newman.sh
# or with overrides:
BASE_URL=http://localhost:8080 ADMIN_USER=admin ADMIN_PASS=admin ./run-newman.sh
```

`run-newman.sh` uses a globally-installed `newman` if present, otherwise
`npx newman`. Runs are serialised under `flock /tmp/uiaudit-nldesign.lock`.

### Two conventions that must not drift

Both of these were broken at once and cost 14 assertions on `development`:

1. **Multipart fixture paths are relative to THIS directory.** The upload
   requests carry `"src": "fixtures/<file>"`. Newman resolves a formdata `src`
   against `--working-dir`, which **defaults to the process CWD**. The shared CI
   workflow (`ConductionNL/.github` → `.github/workflows/quality.yml`, job
   `newman`) does `cd server/apps/<app>/<newman-collection-path>` and then runs
   newman with no `--working-dir`, so in CI the working dir is this directory.
   `run-newman.sh` pins `--working-dir` to the same place. If a path is ever made
   repo-root-relative again it will resolve to nothing in CI, newman will send
   the request **without the file part**, and the app will answer
   `400 No file uploaded.` for a payload the test believes it uploaded — which
   reads as "the upload feature is broken", not "the test cannot find its file".

2. **The CI variable names are aliased in a collection pre-request script.**
   CI passes `base_url` / `admin_user` / `admin_password`; the requests use
   `{{baseUrl}}` / `{{adminUser}}` / `{{adminPass}}`. The collection-level
   `prerequest` script maps the former onto the latter and derives `noAuthBase`
   from the effective base URL. Without it those three flags are inert and the
   suite silently tests whatever is on `localhost:8080` regardless of what it
   was told to target. An explicitly supplied camelCase variable still wins
   (environment scope outranks collection scope), so `run-newman.sh` is
   unaffected.

## Design (matches the procest `tests/integration/` pattern)

- **Host-split auth.** Authenticated requests hit `{{baseUrl}}` (`localhost`)
  with an explicit per-request basic-auth block (`admin:admin`) plus
  `OCS-APIRequest: true` (which lets state-changing POSTs through NC's CSRF
  middleware for an API client). The unauthenticated authz tests hit
  `{{noAuthBase}}` (`127.0.0.1`) so the host-scoped session cookie is never
  sent to them, keeping the 401 assertions honest. Collection auth is `noauth`.
- **`--ignore-redirects`** so an unauthenticated request asserts NC's `401`
  directly instead of following a `303` to the HTML login page.
- Every request sends `Accept: application/json` and `OCS-APIRequest: true`.
- **Idempotent.** Setup captures the live `token_set` and `overrides`; the
  body families assert the read/write/validation round-trips; teardown restores
  both captured values so a run leaves no theming drift.

## Auth posture

Every endpoint is `#[AuthorizedAdminSetting(Admin::class)]`. The `admin` user
is an admin, so authed requests return `200`/`4xx`; unauthenticated requests
are rejected `401`. There is no `#[PublicPage]` endpoint in this app.

## Coverage

46 requests / 108 assertions, all green. Families:

0. Setup — capture live `token_set` + `overrides`.
1. Token sets — list available, set (200), invalid id (400), preview (200),
   unknown preview (404), authz 401 on read + write.
2. Theming + login toggles — get theming values, set hide-slogan, set
   menu-labels, authz 401.
3. Custom token overrides — write (200, written count) + read-back, non-object
   body (400), CSS export (200, `text/css`), import-without-file (400),
   authz 401 on read + write.
4. Custom token sets — multipart upload of a compliant CSS set (200, namespaced
   `custom-*` id + `imported`/`skipped`/`warnings`), name-collision (409),
   disallowed selector (422), malformed JSON (422), missing file (400), missing
   name (400), list, export (200, `text/css`), unknown export (404), delete
   (200), delete of a shipped id (400), authz 401 on upload + list.
   Fixtures live in `fixtures/` — see "Two conventions" above.
10. Teardown — restore the captured token set + overrides.

## Notes

- `overrides` serialises as a JSON object when populated, or `[]` when
  `custom-overrides.css` has no tokens — the setup assertion accepts either.
- Only tokens present in the `TokenRegistry` are accepted by the write
  endpoint; `--color-primary` is a known login-tab token, so writing it yields
  `written: 1`.
- The overrides **import** happy path needs a real multipart `.css` upload via
  the in-browser file-picker — not drivable from a headless client; only its
  validation boundary (no file -> `400`) is asserted here. The happy path is
  covered by Playwright e2e.
