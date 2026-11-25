# email-theming Specification

## Purpose
Nextcloud's notification emails in the house style: thematiq's own email template class, a configurable compliance footer with the organisation's name and its accessibility and privacy links, and an admin switch that sets the mail template in the system config.
## Requirements
### Requirement: NL Design Email Template Class

The app MUST provide a class `OCA\Thematiq\Mail\NLDesignEMailTemplate` that extends
`OC\Mail\EMailTemplate` and is instantiable by `OC\Mail\Mailer::makeTemplate()` with the
server's fixed constructor argument list (the class MUST NOT alter the constructor signature).
Extending the private-namespace `OC\Mail\EMailTemplate` is the sanctioned mechanism here: the
`mail_template_class` system config is the server's own hook and its `is_a(...,
EMailTemplate::class, true)` check requires this parent. The class MUST resolve nldesign
services lazily at render time and MUST fall back to the parent's stock rendering for any part
it cannot derive — a theming failure MUST never prevent an email from being built or sent.

@e2e exclude email rendering — these scenarios assert the markup and colours of a built email message; an email is never rendered in the browser session Playwright drives; proven by tests/Unit/Mail/NLDesignEMailTemplateTest.php and tests/Unit/Service/EmailThemingServiceTest.php.

#### Scenario: Header and buttons use active token set colors and logo

- GIVEN `mail_template_class` is set to `OCA\Thematiq\Mail\NLDesignEMailTemplate`
- AND the active token set is `amsterdam` with `theming.primary_color: #004699` and
  `theming.logo` set in `token-sets.json`
- WHEN the server composes any templated email (e.g. password reset) and `renderHtml()` is
  called
- THEN the header markup MUST use `#004699` where the stock template uses
  `ThemingDefaults::getDefaultColorPrimary()`
- AND the header logo MUST be the absolute URL of the token set's logo asset
- AND body buttons MUST use `#004699` as background with the token set's primary-text color
  (default `#ffffff`) as text color

#### Scenario: Token set without explicit theming metadata falls back to resolved tokens

- GIVEN the active token set has no `theming` object in `token-sets.json`
- WHEN the template derives its colors
- THEN it MUST use the resolved `--nldesign-color-primary` /
  `--nldesign-color-primary-text` values from `TokenSetPreviewService::getResolvedColors()`
- AND if no value can be resolved, it MUST fall back to the parent's `ThemingDefaults`-based
  rendering for that element

#### Scenario: Any theming failure degrades to stock rendering

- GIVEN `mail_template_class` points at the nldesign template
- AND resolving nldesign services throws (app disabled mid-request, unreadable manifest, DI
  failure)
- WHEN an email is composed
- THEN the template MUST render byte-identical to the stock `EMailTemplate` output
- AND no exception MUST escape to the mail-sending code path

#### Scenario: Stale config after app removal does not break mail

- GIVEN `mail_template_class` still names `OCA\Thematiq\Mail\NLDesignEMailTemplate` but the
  nldesign app is disabled or removed (class not autoloadable)
- WHEN `Mailer::makeTemplate()` runs
- THEN the server's own `class_exists` guard (`Mailer.php:133`) falls back to the stock
  template and mail is delivered unbranded — the app relies on this documented server
  behavior and MUST document it in the admin UI help text

### Requirement: Configurable Compliance Footer

The template MUST append a configurable footer block after the standard footer, built from
three nldesign app-config values: `email_footer_org_name` (organization name),
`email_footer_accessibility_url` (toegankelijkheidsverklaring URL), and
`email_footer_privacy_url` (privacy statement URL). Empty values MUST be omitted without
placeholder text. The block MUST appear in BOTH the HTML part and the plain-text part; the
plain-text part MUST otherwise remain intact relative to stock output. Footer text MUST be
HTML-escaped and URLs attribute-escaped in the HTML part; stored URLs MUST be validated to
`http(s)` schemes at write time.

@e2e exclude footer rendering in both mail parts — the assertion spans the HTML and plain-text bodies of a generated message, neither of which exists in a page; proven by tests/Unit/Mail/NLDesignEMailTemplateTest.php and tests/Unit/Service/EmailThemingServiceTest.php.

#### Scenario: Footer renders in both mail parts

- GIVEN footer config org name "Gemeente Voorbeeld",
  accessibility URL `https://voorbeeld.nl/toegankelijkheid`, privacy URL
  `https://voorbeeld.nl/privacy`
- WHEN an email is rendered
- THEN the HTML part MUST contain the org name and both URLs as links labeled with the
  localized strings for "Accessibility statement" and "Privacy statement"
- AND `renderText()` MUST contain the org name and both URLs as plain lines
- AND the plain-text part MUST contain no HTML markup

#### Scenario: Empty footer values are omitted

- GIVEN only `email_footer_org_name` is configured
- WHEN an email is rendered
- THEN only the org name line MUST be appended
- AND no empty link, separator, or placeholder MUST appear for the two unset URLs

#### Scenario: Footer values are output-encoded

- GIVEN an org name containing `<script>alert(1)</script>` was stored
- WHEN the HTML part is rendered
- THEN the value MUST appear HTML-entity-encoded, never as markup
- AND at write time a `javascript:` scheme URL MUST have been rejected with HTTP 422 so it
  can never reach the renderer

### Requirement: Admin Toggle Writes Mail Template System Config

The nldesign admin settings panel MUST provide an "Use NL Design email template" toggle plus
the three footer fields, backed by admin-only endpoints `GET/POST /settings/email-theming`
(`#[AuthorizedAdminSetting]`, CSRF-protected). Enabling MUST set the system config
`mail_template_class` to `OCA\Thematiq\Mail\NLDesignEMailTemplate`; disabling MUST delete the
system value. The service MUST never overwrite a `mail_template_class` value that names a
different class — that state MUST be reported as `foreign` and the toggle disabled with an
explanation. Footer app-config writes MUST be applied independently of the toggle so they
succeed even when the system config cannot be written.

#### Scenario: Admin enables the branded template

- GIVEN an authenticated admin with a writable config.php and no `mail_template_class` set
- WHEN they enable the toggle and save
- THEN `IConfig::setSystemValue('mail_template_class',
  'OCA\\Thematiq\\Mail\\NLDesignEMailTemplate')` MUST be called
- AND the GET endpoint MUST subsequently report state `enabled`

#### Scenario: Foreign template class is never clobbered

- GIVEN `mail_template_class` is set to some other class (e.g. an enterprise template)
- WHEN the admin opens the panel or attempts to enable
- THEN the state MUST be reported as `foreign` including the configured class name
- AND the POST MUST be rejected with HTTP 409 (`error: foreign_mail_template_class`)
  without modifying the system value

#### Scenario: Read-only config.php fails gracefully with occ instructions

- GIVEN the instance has `config_is_read_only => true` (or config.php is
  filesystem-read-only)
- WHEN the admin flips the toggle and saves
- THEN the endpoint MUST return HTTP 409 with `error: config_read_only` and the exact
  command strings
  `occ config:system:set mail_template_class --value "OCA\\Thematiq\\Mail\\NLDesignEMailTemplate"`
  and `occ config:system:delete mail_template_class`
- AND the admin panel MUST revert the checkbox and display those occ commands
- AND footer values submitted in the same request MUST still be saved (app config), with the
  response indicating per-part success

#### Scenario: Non-admin access denied

- GIVEN a non-admin authenticated user
- WHEN they call `GET` or `POST /settings/email-theming`
- THEN the request MUST be rejected by the `AuthorizedAdminSetting` posture, identically to
  the other nldesign settings endpoints

